# 🧪 TESTING & DEBUGGING GUIDE

---

## ✅ Test 1: Module Loading

### Objective
Verifikasi JSZip module ter-load dengan benar

### Steps
```javascript
// 1. Buka browser (F12)
// 2. Buka tab Console
// 3. Paste:
typeof JSZip
```

**Expected Output:** `"function"`

**If Error:** 
```
ReferenceError: JSZip is not defined
```

**Fix:**
```javascript
// Di script setup component, cek import:
import JSZip from 'jszip'  // Harus persis begini

// Bukan:
// import { JSZip } from 'jszip'  ❌
// const JSZip = window.JSZip  ❌
```

---

## ✅ Test 2: Fungsi Helper

### Objective
Test apakah helper functions berfungsi

### Test Case 1: compressToZip()

```javascript
// Di browser console:

// Create test data
const testData = new Uint8Array([
  80, 75, 3, 4, // ZIP header
  14, 0, 0, 0, 8, 0
])

// Test kompresi
window.compressToZip(testData, 'test.xlsx')
  .then(blob => console.log('ZIP created:', blob.size))
  .catch(err => console.error('Error:', err))
```

**Expected Output:**
```
ZIP created: 123  (atau angka tertentu, bukan undefined)
```

### Test Case 2: formatFileSize()

```javascript
// Di console:
window.formatFileSize(1024)
window.formatFileSize(1048576)
window.formatFileSize(500)
```

**Expected Output:**
```
"1 KB"
"1 MB"
"500 Bytes"
```

### Test Case 3: getCompressionInfo()

```javascript
// Di console:
window.getCompressionInfo(1000, 300)
```

**Expected Output:**
```
{
  original: "1000 Bytes",
  compressed: "300 Bytes",
  ratio: "30%",
  saved: "700 Bytes",
  savedPercent: "70%"
}
```

---

## ✅ Test 3: Export Function Full Flow

### Scenario A: Dengan Filter Data

**Steps:**
1. Buka aplikasi di browser
2. Navigasi ke "Export Data Pengukuran"
3. Masukkan filter (opsional):
   - Tanggal Ukur
   - Desa & Posyandu
   - Rentang Berat
4. Klik button "Export"

**Expected Behavior:**
- [ ] Dialog konfirmasi muncul
- [ ] Dialog tampil:
  ```
  Jumlah baris: XX
  Filter Posyandu: Desa X - Posy. Y (jika ada)
  Nama file Excel: UK_DESA_POSYANDU_2024.xlsx
  Opsi Kompresi: File akan disimpan sebagai UK_DESA_POSYANDU_2024.zip
  ```
- [ ] Tombol "✓ Ekspor (Compressed)" dan "Batal" muncul

### Scenario B: Click Ekspor

**Steps:**
1. Klik "✓ Ekspor (Compressed)"

**Expected Behavior:**
- [ ] Dialog loading muncul:
  ```
  Memproses...
  Membuat file Excel & mengkompresi...
  [Loading indicator]
  ```
- [ ] Proses berlangsung (tunggu 1-5 detik)
- [ ] File ZIP auto-download
- [ ] Success alert muncul:
  ```
  ✓ Ekspor Berhasil!
  
  📊 Hasil Ekspor:
  Baris data: XXX
  Nama file: UK_DESA_POSYANDU_2024.zip
  
  📦 Informasi Kompresi:
  Ukuran Awal: 500 KB
  Ukuran Terkompresi: 150 KB
  Rasio Kompresi: 30%
  Hemat Ruang: 350 KB (70%)
  ```

### Scenario C: Verify Downloaded File

**Steps:**
1. Check Downloads folder
2. Pastikan file `UK_DESA_POSYANDU_2024.zip` ada

**Terminal check:**
```bash
# Bash
ls -lh ~/Downloads/UK*.zip

# Expected output:
# -rw-r--r-- 1 user staff 150K Feb 19 10:30 UK_DESA_POSYANDU_2024.zip
```

### Scenario D: Extract & Verify

**Steps:**
```bash
# 1. Extract ZIP
unzip ~/Downloads/UK_DESA_POSYANDU_2024.zip

# 2. Verifikasi file inside
ls -lh UK*.xlsx

# 3. Open Excel
open UK_DESA_POSYANDU_2024.xlsx
# atau double-click di file explorer
```

**Expected Result:**
- [ ] Excel file berhasil extract
- [ ] Excel bisa dibuka normal
- [ ] Data lengkap sesuai filter
- [ ] Header berwarna kuning
- [ ] Format sesuai (NIK bold, tgl teks, dll)

---

## ✅ Test 4: Performance Testing

### Test Case: Large Data (1000+ rows)

**Setup:**
```javascript
// Create test dengan 1000 baris
const largeData = Array.from({length: 1000}, (_, i) => ({
  nik: `${3300000000000 + i}`,
  nama_anak: `Anak ${i+1}`,
  tanggal_ukur: '2024-02-19',
  berat: 10 + Math.random() * 15,
  // ... fields lainnya
}))
```

**Metrics to Check:**
```javascript
// Di console:
console.time('excel-gen')
// ... run export ...
console.timeEnd('excel-gen')

// Expected: < 5 detik untuk 1000 baris
```

**Check File Size:**
```bash
# Before compression
ls -lh *.xlsx  # Harus ada
# Expected: 400-800 KB

# After compression  
ls -lh *.zip  # Harus ada
# Expected: 100-250 KB

# Ratio
# 400 KB → 100 KB = 25% (GOOD)
# 800 KB → 200 KB = 25% (GOOD)
```

---

## 🐛 Common Issues & Solutions

### Issue 1: "Module not found: jszip"

**Symptoms:**
```
Module not found: Can't resolve 'jszip'
```

**Root Cause:** Package tidak installed

**Solution:**
```bash
# 1. Reinstall
npm uninstall jszip
npm install jszip --save

# 2. Clear cache
rm -rf node_modules/.vite
npm cache clean --force

# 3. Restart server
npm run dev
```

### Issue 2: "compressToZip is not defined"

**Symptoms:**
```
TypeError: compressToZip is not a function
```

**Root Cause:** Helper function tidak ada di component

**Solution:**
```javascript
// Pastikan code ini ada di component:

async function compressToZip(buffer, filename) {
  // ... code ...
}

// Bukan di luar component atau di file lain
```

### Issue 3: File ZIP corrupt

**Symptoms:**
```
Error: Archive is not a valid zip file
```

**Root Cause:** Buffer data invalid atau undefined

**Solution:**
```javascript
// Debug di exportUKToExcel():

console.log('excelBuffer:', excelBuffer)
console.log('byteLength:', excelBuffer?.byteLength)
console.log('type:', excelBuffer?.constructor.name)

// Harus:
// byteLength > 0
// type = Uint8Array atau ArrayBuffer
```

### Issue 4: Download tidak terjadi

**Symptoms:**
```
File tidak download ke folder Downloads
```

**Root Cause:** saveAs() gagal atau blocked

**Solution:**
```javascript
// Check di console:
console.log('zipFileName:', zipFileName)
console.log('zipBlob size:', zipBlob.size)
console.log('zipBlob type:', zipBlob.type)

// Test saveAs manually:
const testBlob = new Blob(['test'], {type: 'application/zip'})
saveAs(testBlob, 'test.zip')
```

### Issue 5: Kompresi lambat

**Symptoms:**
```
Loading dialog stuck > 10 detik
```

**Root Cause:** Compression level terlalu tinggi atau data besar

**Solution:**
```javascript
// Turunkan compression level:
compressionOptions: { level: 6 }  // Bukan 9

// Atau optimize data:
// - Kurangi rows
// - Remove unnecessary columns
```

---

## 🧬 Debug Checklist

Copy-paste ini ke browser console untuk full debug:

```javascript
// Check JSZip
console.log('1. JSZip loaded?', typeof JSZip)

// Check helper functions
console.log('2. compressToZip?', typeof window.compressToZip)
console.log('3. formatFileSize?', typeof window.formatFileSize)
console.log('4. getCompressionInfo?', typeof window.getCompressionInfo)

// Test compression
const testBuf = new Uint8Array([1,2,3,4,5])
compressToZip(testBuf, 'test.xlsx')
  .then(blob => console.log('5. Kompresi berhasil:', blob.size))
  .catch(e => console.log('5. Kompresi gagal:', e))

// Check file size formatting
console.log('6. Format 1MB:', formatFileSize(1048576))

// Check compression info
console.log('7. Compression info:', getCompressionInfo(1000, 300))
```

**Expected Output:**
```
1. JSZip loaded? function
2. compressToZip? function
3. formatFileSize? function
4. getCompressionInfo? function
5. Kompresi berhasil: 45
6. Format 1MB: 1 MB
7. Compression info: {original: "1000 Bytes", compressed: "300 Bytes", ratio: "30%", saved: "700 Bytes", savedPercent: "70%"}
```

---

## 📊 Performance Benchmarks

### Expected Times:

| Data Size | Excel Gen | Compress | Total | ZIP Size |
|-----------|-----------|----------|-------|----------|
| 100 rows | 0.2s | 0.1s | 0.3s | 20 KB |
| 500 rows | 0.5s | 0.3s | 0.8s | 80 KB |
| 1000 rows | 1.0s | 0.5s | 1.5s | 150 KB |
| 5000 rows | 4.0s | 2.0s | 6.0s | 700 KB |

---

## 🎯 Acceptance Criteria

Component diterima jika:

- [ ] JSZip installed & loaded
- [ ] Export button works
- [ ] Dialog konfirmasi muncul dengan benar
- [ ] File ZIP generated
- [ ] File size reduced 60-70%
- [ ] ZIP dapat di-extract
- [ ] Excel inside ZIP bisa dibuka
- [ ] Data lengkap sesuai filter
- [ ] Performance acceptable (< 10s)
- [ ] Error handling berfungsi
- [ ] Success message informatif

---

## 📝 Test Report Template

```markdown
# Test Report: JSZip Compression Implementation

## Test Date
2024-02-19

## Environment
- Browser: Chrome/Firefox/Safari
- OS: Windows/Mac/Linux
- Node version: v18+

## Test Results

### Functionality Tests
- [ ] Module loading: PASS/FAIL
- [ ] Helper functions: PASS/FAIL
- [ ] Export with compression: PASS/FAIL
- [ ] File download: PASS/FAIL
- [ ] ZIP integrity: PASS/FAIL
- [ ] Excel extract: PASS/FAIL

### Performance Tests
- [ ] Export 100 rows: X.XXs
- [ ] Export 1000 rows: X.XXs
- [ ] Compression ratio: XX%

### Issues Found
1. Issue 1
2. Issue 2

## Sign-off
Tested by: _______
Date: _______
Status: APPROVED / NEEDS FIX
```

---

**Happy testing! 🚀**
