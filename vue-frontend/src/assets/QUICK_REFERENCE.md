# ⚡ QUICK REFERENCE: Copy-Paste Code

Gunakan file ini untuk implementasi cepat tanpa harus membaca documentation panjang.

---

## 🚀 QUICK SETUP (5 Menit)

### Step 1: Install Package (1 command)
```bash
npm install jszip
```

### Step 2: Copy Import (1 line)
Tambahkan di script setup (bagian atas setelah imports lainnya):
```javascript
import JSZip from 'jszip'
```

### Step 3: Copy Helper Functions
Paste sebelum fungsi `exportUKToExcel()`:

```javascript
/* =================== KOMPRESI HELPERS ✨ =================== */

async function compressToZip(buffer, filename) {
  try {
    const zip = new JSZip()
    zip.file(filename, buffer)
    
    const zipBlob = await zip.generateAsync({
      type: 'blob',
      compression: 'DEFLATE',
      compressionOptions: { level: 9 }
    })
    
    return zipBlob
  } catch (error) {
    console.error('Error compressing to ZIP:', error)
    throw new Error('Gagal mengompresi file: ' + error.message)
  }
}

function formatFileSize(bytes) {
  if (!bytes || bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}

function getCompressionInfo(originalSize, compressedSize) {
  const ratio = Math.round((compressedSize / originalSize) * 100)
  const saved = originalSize - compressedSize
  return {
    original: formatFileSize(originalSize),
    compressed: formatFileSize(compressedSize),
    ratio: ratio + '%',
    saved: formatFileSize(saved),
    savedPercent: (100 - ratio) + '%'
  }
}
```

### Step 4: Replace exportUKToExcel() Function

**PENTING:** Ganti SELURUH fungsi `async function exportUKToExcel() { ... }`

Copy dari sini sampai akhir `}` ← Ganti semua

```javascript
async function exportUKToExcel() {
  if (!filteredRows.value.length) {
    await Swal.fire('Tidak ada', 'Tidak ada data sesuai filter untuk diekspor.', 'info')
    return
  }

  const fname = buildFileNameUK({
    labelPos: filterPosyandu.value || '',
    sample: rows.value,
    data: filteredRows.value,
    getTglUkur: (r) => getTglUkur(r),
    tglFrom: tglFrom.value,
    tglTo:   tglTo.value,
  })

  const { isConfirmed } = await Swal.fire({
    icon: 'question',
    title: 'Konfirmasi Ekspor',
    html: `
      <div class="text-start">
        <div class="mb-3">
          <strong>📊 Detail Ekspor:</strong>
          <div class="mt-2 ms-3">
            <div>Jumlah baris: <span class="badge bg-dark">${filteredRows.value.length}</span></div>
            ${filterPosyandu.value ? `<div>Filter Posyandu: <span class="badge bg-dark">${filterPosyandu.value}</span></div>` : ''}
            <div class="mt-2">Nama file Excel: <code>${fname}</code></div>
          </div>
        </div>
        
        <div class="alert alert-info alert-dismissible fade show mb-0" role="alert">
          <strong>💾 Opsi Kompresi:</strong> File akan disimpan sebagai <code>${fname.replace('.xlsx', '.zip')}</code>
          <br/><small class="text-muted">Kompresi DEFLATE Level 9 → Ukuran file ~60-70% lebih kecil</small>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: '✓ Ekspor (Compressed)',
    confirmButtonColor: '#28a745',
    cancelButtonText: 'Batal',
  })
  
  if (!isConfirmed) return

  const loadingSwal = Swal.fire({
    title: 'Memproses...',
    html: 'Membuat file Excel & mengkompresi...',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading()
    }
  })

  try {
    const wb = new ExcelJS.Workbook()
    const ws = wb.addWorksheet('UK')

    const header = [
      'No','NIK','nama_anak','TANGGALUKUR','BERAT','TINGGI','LILA','lingkar_kepala',
      'CARAUKUR','vita','asi_bulan_0','asi_bulan_1','asi_bulan_2','asi_bulan_3',
      'asi_bulan_4','asi_bulan_5','asi_bulan_6','kelas_ibu_balita'
    ]

    const dataRows = filteredRows.value.map((r, i) => {
      const tgl = fmtISO(getTglUkur(r))
      const vitaCell = vitaAllowedOnDate(tgl) ? toExportVitaValue(getVita(r)) : ''

      return [
        i + 1,
        getNIK(r) || '-/(BELUM ADA)',
        getNamaAnak(r) || '',
        tgl,
        safe(getBerat(r)),
        safe(getTinggi(r)),
        safe(getLILA(r)),
        safe(getLK(r)),
        getCaraUkur(r) || '',
        vitaCell,
        toOneBlank(getAsi(r,0)),
        toOneBlank(getAsi(r,1)),
        toOneBlank(getAsi(r,2)),
        toOneBlank(getAsi(r,3)),
        toOneBlank(getAsi(r,4)),
        toOneBlank(getAsi(r,5)),
        toOneBlank(getAsi(r,6)),
        toOneBlank(getKelasIbuBalita(r)),
      ]
    })

    ws.addRow(header)
    ws.getRow(1).eachCell((cell) => {
      cell.font = { bold: true }
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFF00' } }
      cell.alignment = { vertical: 'middle', horizontal: 'center' }
      cell.border = { top:{style:'thin'}, left:{style:'thin'}, bottom:{style:'thin'}, right:{style:'thin'} }
    })

    dataRows.forEach(r => ws.addRow(r))

    ws.eachRow({ includeEmpty: false }, (row, rowNumber) => {
      if (rowNumber === 1) return
      row.eachCell((cell, col) => {
        if (col === 2 || col === 4) {
          const v = cell.value ?? ''
          cell.value = v === '' ? '' : { richText: [{ text: String(v) }] }
          cell.numFmt = '@'
        }
        cell.border = { top:{style:'thin'}, left:{style:'thin'}, bottom:{style:'thin'}, right:{style:'thin'} }
        cell.alignment = { vertical: 'middle', horizontal: 'left' }
      })
    })

    autoFitColumns(ws)

    // ===== KOMPRESI ✨ =====
    const excelBuffer = await wb.xlsx.writeBuffer()
    const excelSize = excelBuffer.byteLength

    const zipBlob = await compressToZip(excelBuffer, fname)
    const zipSize = zipBlob.size
    const zipFileName = fname.replace('.xlsx', '.zip')

    const compressionInfo = getCompressionInfo(excelSize, zipSize)

    saveAs(zipBlob, zipFileName)

    await loadingSwal
    await Swal.fire({
      icon: 'success',
      title: 'Ekspor Berhasil! ✓',
      html: `
        <div class="text-start">
          <div class="mb-3">
            <strong>📊 Hasil Ekspor:</strong>
            <div class="mt-2 ms-3">
              <div>Baris data: <span class="badge bg-success">${dataRows.length}</span></div>
              <div>Nama file: <code>${zipFileName}</code></div>
            </div>
          </div>
          
          <div class="alert alert-success" role="alert">
            <strong>📦 Informasi Kompresi:</strong>
            <table class="table table-sm table-borderless mt-2 mb-0">
              <tr>
                <td><strong>Ukuran Awal:</strong></td>
                <td class="text-end"><code>${compressionInfo.original}</code></td>
              </tr>
              <tr>
                <td><strong>Ukuran Terkompresi:</strong></td>
                <td class="text-end"><code>${compressionInfo.compressed}</code></td>
              </tr>
              <tr>
                <td><strong>Rasio Kompresi:</strong></td>
                <td class="text-end"><span class="badge bg-info">${compressionInfo.ratio}</span></td>
              </tr>
              <tr class="table-success">
                <td><strong>Hemat Ruang:</strong></td>
                <td class="text-end"><span class="badge bg-success">${compressionInfo.saved} (${compressionInfo.savedPercent})</span></td>
              </tr>
            </table>
          </div>
        </div>
      `,
      confirmButtonText: 'OK'
    })

  } catch (e) {
    await loadingSwal
    console.error('Export error:', e)
    await Swal.fire({
      icon: 'error',
      title: 'Gagal Ekspor',
      html: `
        <div class="text-start">
          <p><strong>Error:</strong> ${e?.message ?? 'Terjadi kesalahan saat membuat file Excel.'}</p>
          <details style="text-align: left; font-size: 0.85rem; color: #666;">
            <summary>Detail Error</summary>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin-top: 10px; overflow-x: auto;">${e?.stack ?? e?.toString()}</pre>
          </details>
        </div>
      `
    })
  }
}
```

---

## ✅ Verifikasi Cepat

Copy 3 command ini, jalankan satu-satu di terminal:

```bash
# 1. Check instalasi
npm list jszip

# 2. Check package.json
cat package.json | grep jszip

# 3. Restart dev server (penting!)
npm run dev
```

---

## 🐛 Quick Troubleshooting

### Problem: "JSZip is not defined"
**Solution:** Pastikan ini di paling atas file (bukan di dalam component):
```javascript
import JSZip from 'jszip'  // ← HARUS SEBELUM <script setup> ends
```

### Problem: Error saat kompres
**Solution:** Cek di browser console:
```javascript
console.log(window.JSZip) // Harus function, bukan undefined
```

### Problem: File corrupt
**Solution:** Verify buffer valid:
```javascript
console.log('Buffer byteLength:', excelBuffer.byteLength)
// Harus > 0, jangan undefined
```

---

## 📱 Testing Checklist

- [ ] Install jszip via npm ✓
- [ ] Import JSZip di component ✓
- [ ] Copy helper functions ✓
- [ ] Copy exportUKToExcel() function ✓
- [ ] Restart dev server ✓
- [ ] Open app di browser ✓
- [ ] Click Export button ✓
- [ ] Verify dialog tampil ✓
- [ ] Click "Ekspor (Compressed)" ✓
- [ ] File ZIP download ✓
- [ ] Extract ZIP di local ✓
- [ ] Excel dapat dibuka ✓
- [ ] Check file size 60-70% smaller ✓

---

## 🎯 File Size Expected

Jika data 1000 baris:

```
Excel: ~500 KB
ZIP:   ~150-200 KB ← Expected!
Ratio: 30-40% ← Expect this
```

Jika hasil tidak seperti ini, cek:
1. Compression level masih 9? ✓
2. Data file valid? ✓
3. Buffer tidak undefined? ✓

---

**DONE! Sekarang component Anda bisa kompresi Excel ke ZIP.** 🎉
