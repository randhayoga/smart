/**
 * Utility to export tabular data to a genuine Microsoft Excel (.xlsx) file without external dependencies.
 *
 * @param headers - Array of header titles
 * @param rows - Array of row data arrays
 * @param filename - Base filename without extension or with .xlsx
 */

function calcCRC32(data: Uint8Array): number {
  let crc = 0xffffffff;
  for (let i = 0; i < data.length; i++) {
    crc ^= data[i];
    for (let j = 0; j < 8; j++) {
      crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
    }
  }
  return (crc ^ 0xffffffff) >>> 0;
}

interface ZipEntry {
  name: string;
  data: Uint8Array;
}

function buildZip(files: ZipEntry[]): Uint8Array {
  const encoder = new TextEncoder();
  const localHeaders: Uint8Array[] = [];
  const centralDirs: Uint8Array[] = [];
  let currentOffset = 0;

  for (const file of files) {
    const fileNameBytes = encoder.encode(file.name);
    const crc = calcCRC32(file.data);
    const size = file.data.length;

    // Local file header (30 bytes + filename + data)
    const localHeader = new Uint8Array(30 + fileNameBytes.length + size);
    const view = new DataView(localHeader.buffer);

    view.setUint32(0, 0x04034b50, true); // Local header signature
    view.setUint16(4, 20, true); // Version needed (2.0)
    view.setUint16(6, 0, true); // General purpose flag
    view.setUint16(8, 0, true); // Compression method (0 = store)
    view.setUint16(10, 0, true); // Last mod time
    view.setUint16(12, 0, true); // Last mod date
    view.setUint32(14, crc, true); // CRC-32
    view.setUint32(18, size, true); // Compressed size
    view.setUint32(22, size, true); // Uncompressed size
    view.setUint16(26, fileNameBytes.length, true); // Filename length
    view.setUint16(28, 0, true); // Extra field length

    localHeader.set(fileNameBytes, 30);
    localHeader.set(file.data, 30 + fileNameBytes.length);
    localHeaders.push(localHeader);

    // Central directory header (46 bytes + filename)
    const centralDir = new Uint8Array(46 + fileNameBytes.length);
    const cdView = new DataView(centralDir.buffer);

    cdView.setUint32(0, 0x02014b50, true); // Central dir signature
    cdView.setUint16(4, 20, true); // Version made by
    cdView.setUint16(6, 20, true); // Version needed
    cdView.setUint16(8, 0, true); // General purpose flag
    cdView.setUint16(10, 0, true); // Compression method
    cdView.setUint16(12, 0, true); // Last mod time
    cdView.setUint16(14, 0, true); // Last mod date
    cdView.setUint32(16, crc, true); // CRC-32
    cdView.setUint32(20, size, true); // Compressed size
    cdView.setUint32(24, size, true); // Uncompressed size
    cdView.setUint16(28, fileNameBytes.length, true); // Filename length
    cdView.setUint16(30, 0, true); // Extra field length
    cdView.setUint16(32, 0, true); // Comment length
    cdView.setUint16(34, 0, true); // Disk start
    cdView.setUint16(36, 0, true); // Internal attributes
    cdView.setUint32(38, 0, true); // External attributes
    cdView.setUint32(42, currentOffset, true); // Local header offset

    centralDir.set(fileNameBytes, 46);
    centralDirs.push(centralDir);

    currentOffset += localHeader.length;
  }

  const centralDirOffset = currentOffset;
  let centralDirSize = 0;
  for (const cd of centralDirs) {
    centralDirSize += cd.length;
  }

  // End of Central Directory (EOCD) (22 bytes)
  const eocd = new Uint8Array(22);
  const eocdView = new DataView(eocd.buffer);
  eocdView.setUint32(0, 0x06054b50, true); // EOCD signature
  eocdView.setUint16(4, 0, true); // Disk number
  eocdView.setUint16(6, 0, true); // Disk with central dir
  eocdView.setUint16(8, files.length, true); // Entries on disk
  eocdView.setUint16(10, files.length, true); // Total entries
  eocdView.setUint32(12, centralDirSize, true); // Central dir size
  eocdView.setUint32(16, centralDirOffset, true); // Central dir offset
  eocdView.setUint16(20, 0, true); // Comment length

  const totalLength = centralDirOffset + centralDirSize + 22;
  const result = new Uint8Array(totalLength);

  let offset = 0;
  for (const lh of localHeaders) {
    result.set(lh, offset);
    offset += lh.length;
  }
  for (const cd of centralDirs) {
    result.set(cd, offset);
    offset += cd.length;
  }
  result.set(eocd, offset);

  return result;
}

function escapeXML(str: any): string {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

function getColumnLetter(colIndex: number): string {
  let letter = '';
  while (colIndex >= 0) {
    const temp = colIndex % 26;
    letter = String.fromCharCode(temp + 65) + letter;
    colIndex = Math.floor(colIndex / 26) - 1;
  }
  return letter;
}

export function exportExcel(
  headers: string[],
  rows: (string | number | null | undefined)[][],
  filename: string = 'export'
): void {
  if (!rows || rows.length === 0) return;

  const textEncoder = new TextEncoder();

  // 1. Generate Sheet XML
  let sheetXML = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>`;

  // Row 1: Header Row
  sheetXML += `<row r="1">`;
  headers.forEach((h, colIdx) => {
    const colRef = getColumnLetter(colIdx) + '1';
    sheetXML += `<c r="${colRef}" t="inlineStr" s="1"><is><t>${escapeXML(h)}</t></is></c>`;
  });
  sheetXML += `</row>`;

  // Data Rows
  rows.forEach((row, rowIdx) => {
    const rNum = rowIdx + 2;
    sheetXML += `<row r="${rNum}">`;
    row.forEach((cellVal, colIdx) => {
      const colRef = getColumnLetter(colIdx) + rNum;
      if (typeof cellVal === 'number') {
        sheetXML += `<c r="${colRef}"><v>${cellVal}</v></c>`;
      } else {
        sheetXML += `<c r="${colRef}" t="inlineStr"><is><t>${escapeXML(cellVal ?? '')}</t></is></c>`;
      }
    });
    sheetXML += `</row>`;
  });

  sheetXML += `</sheetData>
</worksheet>`;

  // 2. OpenXML templates
  const contentTypesXML = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>`;

  const relsXML = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>`;

  const workbookRelsXML = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>`;

  const workbookXML = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>`;

  const stylesXML = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>
  </cellXfs>
</styleSheet>`;

  const files: ZipEntry[] = [
    { name: '[Content_Types].xml', data: textEncoder.encode(contentTypesXML) },
    { name: '_rels/.rels', data: textEncoder.encode(relsXML) },
    { name: 'xl/_rels/workbook.xml.rels', data: textEncoder.encode(workbookRelsXML) },
    { name: 'xl/workbook.xml', data: textEncoder.encode(workbookXML) },
    { name: 'xl/styles.xml', data: textEncoder.encode(stylesXML) },
    { name: 'xl/worksheets/sheet1.xml', data: textEncoder.encode(sheetXML) },
  ];

  const zipBytes = buildZip(files);
  const blob = new Blob([zipBytes.buffer as ArrayBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);

  const cleanFilename = filename.endsWith('.xlsx') ? filename : `${filename}_${Date.now()}.xlsx`;
  link.setAttribute('download', cleanFilename);

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
