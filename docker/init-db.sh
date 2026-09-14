#!/bin/bash
set -e

# ==============================================================================
# MSSQL Database Initialization & Automated Restoration Script
# ==============================================================================

DB_NAME="${DB_SMART_DATABASE:-SMART}"
DB_TEST_NAME="${DB_SMART_TEST_DATABASE:-${DB_NAME}_TESTING}"
BACKUP_DIR="/var/opt/mssql/backup"

echo "==> Waiting for SQL Server to be ready..."
until /opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -Q "SELECT 1" &> /dev/null
do
    sleep 2
done

# ------------------------------------------------------------------------------
# 1. Ensure SMART Primary and Testing Databases Exist
# ------------------------------------------------------------------------------
for TARGET_DB in "$DB_NAME" "$DB_TEST_NAME"
do
    echo "==> Ensuring database [$TARGET_DB] exists..."
    /opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -Q "
    IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = N'$TARGET_DB')
    BEGIN
        CREATE DATABASE [$TARGET_DB];
        PRINT 'Database [$TARGET_DB] created successfully.';
    END
    ELSE
    BEGIN
        PRINT 'Database [$TARGET_DB] already exists.';
    END
    "

    # If a non-SA user is specified, create the login and map as db_owner
    if [ -n "$DB_SMART_USERNAME" ] && [ "$DB_SMART_USERNAME" != "sa" ] && [ "$DB_SMART_USERNAME" != "SA" ] && [ -n "$DB_SMART_PASSWORD" ]; then
        echo "==> Configuring user [$DB_SMART_USERNAME] for database [$TARGET_DB]..."
        /opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -Q "
        IF NOT EXISTS (SELECT name FROM sys.server_principals WHERE name = N'$DB_SMART_USERNAME')
        BEGIN
            CREATE LOGIN [$DB_SMART_USERNAME] WITH PASSWORD = '$DB_SMART_PASSWORD', CHECK_POLICY = OFF;
            PRINT 'Login [$DB_SMART_USERNAME] created.';
        END
        USE [$TARGET_DB];
        IF NOT EXISTS (SELECT name FROM sys.database_principals WHERE name = N'$DB_SMART_USERNAME')
        BEGIN
            CREATE USER [$DB_SMART_USERNAME] FOR LOGIN [$DB_SMART_USERNAME];
            ALTER ROLE db_owner ADD MEMBER [$DB_SMART_USERNAME];
            PRINT 'User [$DB_SMART_USERNAME] granted db_owner on [$TARGET_DB].';
        END
        "
    fi
done

# ------------------------------------------------------------------------------
# 2. Automated Restoration for External Application Databases
# ------------------------------------------------------------------------------
restore_db_if_needed() {
    local TARGET_DB="$1"
    local BAK_PATTERN="$2"

    # Locate the latest matching backup file in BACKUP_DIR
    local BAK_FILE
    BAK_FILE=$(ls -1t ${BACKUP_DIR}/${BAK_PATTERN} 2>/dev/null | head -n 1 || true)

    if [ -z "$BAK_FILE" ] || [ ! -f "$BAK_FILE" ]; then
        echo "==> [NOTICE] No backup file matching '${BAK_PATTERN}' found in ${BACKUP_DIR}. Skipping restore for [$TARGET_DB]."
        return 0
    fi

    echo "==> Checking status of external database [$TARGET_DB]..."

    # Check if DB exists and whether it has base tables
    local DB_STATUS
    DB_STATUS=$(/opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -h -1 -W -Q "
    SET NOCOUNT ON;
    DECLARE @exists INT = 0;
    DECLARE @tables INT = 0;
    IF EXISTS (SELECT name FROM sys.databases WHERE name = N'$TARGET_DB')
    BEGIN
        SET @exists = 1;
        DECLARE @sql NVARCHAR(MAX) = N'SELECT @cnt = COUNT(*) FROM [$TARGET_DB].INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = ''BASE TABLE''';
        EXEC sp_executesql @sql, N'@cnt INT OUTPUT', @cnt = @tables OUTPUT;
    END;
    SELECT CONCAT(@exists, ':', @tables);
    ")

    local EXISTS
    EXISTS=$(echo "$DB_STATUS" | cut -d':' -f1 | tr -d '[:space:]')
    local TABLES
    TABLES=$(echo "$DB_STATUS" | cut -d':' -f2 | tr -d '[:space:]')

    if [ "$EXISTS" = "1" ] && [ "$TABLES" -gt 0 ]; then
        echo "==> Database [$TARGET_DB] already exists with $TABLES tables. Skipping restore."
    else
        echo "==> Restoring database [$TARGET_DB] from $BAK_FILE (this may take a moment)..."

        /opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -Q "
        SET NOCOUNT ON;

        IF EXISTS (SELECT name FROM sys.databases WHERE name = N'$TARGET_DB')
        BEGIN
            ALTER DATABASE [$TARGET_DB] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
            DROP DATABASE [$TARGET_DB];
        END

        DECLARE @fileList TABLE (
            LogicalName NVARCHAR(128),
            PhysicalName NVARCHAR(260),
            Type CHAR(1),
            FileGroupName NVARCHAR(128),
            Size NUMERIC(20,0),
            MaxSize NUMERIC(20,0),
            FileId BIGINT,
            CreateLSN NUMERIC(25,0),
            DropLSN NUMERIC(25,0),
            UniqueId UNIQUEIDENTIFIER,
            ReadOnlyLSN NUMERIC(25,0),
            ReadWriteLSN NUMERIC(25,0),
            BackupSizeInBytes BIGINT,
            SourceBlockSize INT,
            FileGroupId INT,
            LogGroupGUID UNIQUEIDENTIFIER,
            DifferentialBaseLSN NUMERIC(25,0),
            DifferentialBaseGUID UNIQUEIDENTIFIER,
            IsReadOnly BIT,
            IsPresent BIT,
            TDEThumbprint VARBINARY(32),
            SnapshotUrl NVARCHAR(360)
        );

        INSERT INTO @fileList
        EXEC('RESTORE FILELISTONLY FROM DISK = N''' + '$BAK_FILE' + '''');

        DECLARE @dataLogical NVARCHAR(128) = (SELECT TOP 1 LogicalName FROM @fileList WHERE Type = 'D');
        DECLARE @logLogical NVARCHAR(128) = (SELECT TOP 1 LogicalName FROM @fileList WHERE Type = 'L');

        DECLARE @restoreSql NVARCHAR(MAX) = N'RESTORE DATABASE [$TARGET_DB] FROM DISK = N''' + '$BAK_FILE' + ''' WITH ' +
            N'MOVE N''' + @dataLogical + N''' TO N''/var/opt/mssql/data/${TARGET_DB}.mdf'', ' +
            N'MOVE N''' + @logLogical + N''' TO N''/var/opt/mssql/data/${TARGET_DB}_log.ldf'', ' +
            N'REPLACE, STATS = 20;';

        EXEC (@restoreSql);
        PRINT 'Database [$TARGET_DB] successfully restored.';
        "

        # If RE_PORTALDB, disable legacy external triggers referencing RKEMAS
        if [ "$TARGET_DB" = "RE_PORTALDB" ]; then
            /opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -d RE_PORTALDB -Q "
            IF EXISTS (SELECT 1 FROM sys.triggers WHERE name = 'tb_project_AI') DISABLE TRIGGER [dbo].[tb_project_AI] ON [dbo].[tb_project];
            IF EXISTS (SELECT 1 FROM sys.triggers WHERE name = 'tb_project_AU') DISABLE TRIGGER [dbo].[tb_project_AU] ON [dbo].[tb_project];
            "
        fi

        echo "==> Database [$TARGET_DB] restored successfully."
    fi

    # Map application user permissions if configured
    if [ -n "$DB_SMART_USERNAME" ] && [ "$DB_SMART_USERNAME" != "sa" ] && [ "$DB_SMART_USERNAME" != "SA" ] && [ -n "$DB_SMART_PASSWORD" ]; then
        echo "==> Granting user [$DB_SMART_USERNAME] access to [$TARGET_DB]..."
        /opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -Q "
        USE [$TARGET_DB];
        IF NOT EXISTS (SELECT name FROM sys.database_principals WHERE name = N'$DB_SMART_USERNAME')
        BEGIN
            CREATE USER [$DB_SMART_USERNAME] FOR LOGIN [$DB_SMART_USERNAME];
        END
        ELSE
        BEGIN
            ALTER USER [$DB_SMART_USERNAME] WITH LOGIN = [$DB_SMART_USERNAME];
        END;
        ALTER ROLE db_owner ADD MEMBER [$DB_SMART_USERNAME];
        PRINT 'User [$DB_SMART_USERNAME] granted db_owner on [$TARGET_DB].';
        "
    fi
}

# Run restore checks for the 3 databases
restore_db_if_needed "RE_PORTALDB" "RE_PORTALDB*.bak"
restore_db_if_needed "USER_HRIS" "USER_HRIS*.bak"
restore_db_if_needed "new_portal" "new_portal*.bak"

echo "==> Database initialization and restore verification completed successfully!"
