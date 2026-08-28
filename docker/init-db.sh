#!/bin/bash
set -e

DB_NAME="${DB_SMART_DATABASE:-SMART}"

echo "==> Waiting for SQL Server to be ready..."
until /opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -Q "SELECT 1" &> /dev/null
do
    sleep 2
done

echo "==> Ensuring database [$DB_NAME] exists..."
/opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -Q "
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = N'$DB_NAME')
BEGIN
    CREATE DATABASE [$DB_NAME];
    PRINT 'Database [$DB_NAME] created successfully.';
END
ELSE
BEGIN
    PRINT 'Database [$DB_NAME] already exists.';
END
"

# If a non-SA user is specified, create the login and map as db_owner
if [ -n "$DB_SMART_USERNAME" ] && [ "$DB_SMART_USERNAME" != "sa" ] && [ "$DB_SMART_USERNAME" != "SA" ] && [ -n "$DB_SMART_PASSWORD" ]; then
    echo "==> Configuring user [$DB_SMART_USERNAME] for database [$DB_NAME]..."
    /opt/mssql-tools18/bin/sqlcmd -S db -U SA -P "$MSSQL_SA_PASSWORD" -C -Q "
    IF NOT EXISTS (SELECT name FROM sys.server_principals WHERE name = N'$DB_SMART_USERNAME')
    BEGIN
        CREATE LOGIN [$DB_SMART_USERNAME] WITH PASSWORD = '$DB_SMART_PASSWORD', CHECK_POLICY = OFF;
        PRINT 'Login [$DB_SMART_USERNAME] created.';
    END
    USE [$DB_NAME];
    IF NOT EXISTS (SELECT name FROM sys.database_principals WHERE name = N'$DB_SMART_USERNAME')
    BEGIN
        CREATE USER [$DB_SMART_USERNAME] FOR LOGIN [$DB_SMART_USERNAME];
        ALTER ROLE db_owner ADD MEMBER [$DB_SMART_USERNAME];
        PRINT 'User [$DB_SMART_USERNAME] granted db_owner on [$DB_NAME].';
    END
    "
fi

echo "==> Database initialization completed successfully!"
