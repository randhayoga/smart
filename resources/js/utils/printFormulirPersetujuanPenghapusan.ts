export interface PrintableAssetItem {
  id?: number | string;
  number?: string | null;
  code?: string | null;
  barang_code?: string | null;
  barang_nama?: string | null;
  name?: string | null;
  barang_name?: string | null;
  barang_brand?: string | null;
  brand?: string | null;
  merek?: string | null;
  barang_category?: string | null;
  category?: any;
  category_name?: string | null;
  barang_subcategory?: string | null;
  subcategory?: any;
  subcategory_name?: string | null;
  condition?: string | null;
}

function getStringVal(val: any): string {
  if (!val) return '';
  if (typeof val === 'string') return val.trim();
  if (typeof val === 'object' && val.name) return String(val.name).trim();
  return String(val).trim();
}

function escapeHtml(str: string): string {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Print utility rendering the asset disposal approval form in A4 landscape format.
 *
 * @param items - List of printable asset items
 */
export function printFormulirPersetujuanPenghapusan(
  items: PrintableAssetItem[]
): void {
  const rowsHtml = items.map((item, index) => {
    const no = index + 1;
    const number = getStringVal(item.number || item.code || item.barang_code) || '-';
    const name = getStringVal(item.barang_nama || item.name || item.barang_name) || '-';
    const brand = getStringVal(item.barang_brand || item.brand || item.merek) || '-';

    const cat = getStringVal(item.barang_category || item.category || item.category_name);
    const subcat = getStringVal(item.barang_subcategory || item.subcategory || item.subcategory_name);

    let categoryText = cat;
    if (cat && subcat) {
      categoryText = `${cat}: ${subcat}`;
    } else if (!cat && subcat) {
      categoryText = subcat;
    }
    if (!categoryText) categoryText = '-';

    const conditionText = getStringVal(item.condition) || '-';

    return `
      <tr>
        <td class="text-center">${no}</td>
        <td>${escapeHtml(number)}</td>
        <td>${escapeHtml(name)}</td>
        <td>${escapeHtml(brand)}</td>
        <td>${escapeHtml(categoryText)}</td>
        <td class="text-center">${escapeHtml(conditionText)}</td>
      </tr>
    `;
  }).join('');

  const htmlContent = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title></title>
  <style>
    @page {
      size: A4 landscape;
      margin: 0;
    }
    * {
      box-sizing: border-box;
    }
    body {
      font-family: Arial, Helvetica, sans-serif;
      margin: 0;
      padding: 12mm 15mm;
      color: #000;
      background: #fff;
    }
    .header {
      text-align: center;
      margin-bottom: 16px;
    }
    .title {
      font-size: 13pt;
      font-weight: bold;
      margin: 0 0 4px 0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      font-size: 8.5pt;
    }
    th, td {
      border: 1px solid #333;
      padding: 4px 6px;
      white-space: nowrap;
      text-align: left;
    }
    th {
      background-color: #f1f5f9 !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
      font-weight: bold;
      text-align: center;
    }
    td.text-center {
      text-align: center;
    }
    tr {
      page-break-inside: avoid;
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="title">FORMULIR PERSETUJUAN PENGHAPUSAN ASSET PT REKAYASA ENGINEERING</div>
  </div>
  <table>
    <thead>
      <tr>
        <th style="width: 35px;">No</th>
        <th>Nomor Aset</th>
        <th>Nama</th>
        <th>Merek</th>
        <th>Kategori</th>
        <th style="width: 120px;">Kondisi</th>
      </tr>
    </thead>
    <tbody>
      ${rowsHtml}
    </tbody>
  </table>
</body>
</html>`;

  const iframe = document.createElement('iframe');
  iframe.style.position = 'fixed';
  iframe.style.right = '0';
  iframe.style.bottom = '0';
  iframe.style.width = '0';
  iframe.style.height = '0';
  iframe.style.border = '0';
  document.body.appendChild(iframe);

  const doc = iframe.contentWindow?.document;
  if (doc) {
    doc.open();
    doc.write(htmlContent);
    doc.close();

    iframe.contentWindow?.focus();
    setTimeout(() => {
      iframe.contentWindow?.print();
      setTimeout(() => {
        if (document.body.contains(iframe)) {
          document.body.removeChild(iframe);
        }
      }, 1000);
    }, 250);
  }
}
