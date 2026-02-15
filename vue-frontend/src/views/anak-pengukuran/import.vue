<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'
import Swal from 'sweetalert2'
import * as XLSX from 'xlsx'
import ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'

const router = useRouter()

/* ====== CONFIG ====== */
const AGE_SKIP_MONTHS = 60

const MONTHS = [
  { n:1,  long:'JANUARI',  syn:['1','JAN','JANUARI'] },
  { n:2,  long:'FEBRUARI', syn:['2','FEB','FEBRUARI'] },
  { n:3,  long:'MARET',    syn:['3','MAR','MARET','MART'] },
  { n:4,  long:'APRIL',    syn:['4','APR','APRIL'] },
  { n:5,  long:'MEI',      syn:['5','MEI'] },
  { n:6,  long:'JUNI',     syn:['6','JUN','JUNI'] },
  { n:7,  long:'JULI',     syn:['7','JUL','JULI'] },
  { n:8,  long:'AGUSTUS',  syn:['8','AGU','AGS','AGUST','AGUSTUS'] },
  { n:9,  long:'SEPTEMBER',syn:['9','SEP','SEPT','SEPTEMBER'] },
  { n:10, long:'OKTOBER',  syn:['10','OKT','OKTOBER'] },
  { n:11, long:'NOVEMBER', syn:['11','NOV','NOVEMBER'] },
  { n:12, long:'DESEMBER', syn:['12','DES','DESEMBER'] },
]
const byNum = Object.fromEntries(MONTHS.map(m => [m.n, m]))
const byName = {}; for (const m of MONTHS) for (const s of m.syn) byName[s.toUpperCase()] = m

/* ====== STATE UI (MULTI-SHEET) ====== */
const parsing = ref(false)
const startInfo = reactive({ month:null, year:null, label:'-' })
const stateDesaStart = ref('')  // desa dari sheet Start

// Struktur multi-sheet: [{ name:'P1', rows:[{ ... }], preview:[], checking:false, ... }]
const sheets = ref([])

const importing = ref(false)
const progress = reactive({ total:0, done:0 })

/* ====== PROGRESS CEK (PREVIEW) GLOBAL ====== */
const checkingAll = ref(false)
const checkProgress = reactive({ total:0, done:0 })

/* ====== POSYANDU LIST ====== */
const posList = ref([])
const loadingPos = ref(false)
async function loadPosyandu() {
  loadingPos.value = true
  try {
    const r = await api.get('/posyandu', { headers:{ Accept:'application/json' } })
    const data =
      Array.isArray(r.data) ? r.data :
      Array.isArray(r?.data?.data) ? r.data.data :
      Array.isArray(r?.data?.data?.data) ? r.data.data.data : []
    posList.value = data.map(p => ({
      id: p.id,
      desa: String(p?.desa||'').toUpperCase().trim(),
      nama: String(p?.nama||'').toUpperCase().trim(),
      label: `DESA ${String(p?.desa||'').toUpperCase().trim()} POSY ${String(p?.nama||'').toUpperCase().trim()}`,
      num: (String(p?.nama||'').match(/\d+/)?.[0]) || ''
    }))
  } finally { loadingPos.value = false }
}
onMounted(loadPosyandu)

/* ====== HELPERS ====== */
const digits = (s) => String(s||'').replace(/\D/g,'')
const toFloat = (v) => {
  if (v==null || v==='') return null
  const s = String(v).replace(',', '.').replace(/[^\d.\-]/g,'').trim()
  const f = parseFloat(s)
  return Number.isFinite(f) ? f : null
}
const toBool = (v) => {
  if (v === true || v === 1 || v === '1') return true
  const s = String(v ?? '').trim().toLowerCase()
  return ['y','ya','yes','true','1','iya','✓','✔'].includes(s)
}
function toVitFlag(v) {
  const s = String(v ?? '').trim()
  if (!s) return false
  if (isTrivialPlaceholder(s)) return false
  return true
}
function ymdFromMonth(year, monthNum) {
  const y = String(year), m = String(monthNum).padStart(2,'0')
  return `${y}-${m}-01`
}
function diffMonths(dobYmd, refYmd) {
  if (!dobYmd || !refYmd) return null
  const [y1,m1,d1] = dobYmd.split('-').map(Number)
  const [y2,m2,d2] = refYmd.split('-').map(Number)
  let months = (y2 - y1) * 12 + (m2 - m1)
  if (d2 < d1) months -= 1
  return months
}
function parseJK(v) {
  const s = String(v ?? '').trim().toUpperCase()
  if (s === 'L' || s === '1') return 'L'
  if (s === 'P' || s === '2') return 'P'
  return ''
}
function normName(s) {
  return String(s||'')
    .normalize('NFKD').replace(/[\u0300-\u036f]/g,'')
    .toUpperCase()
    .replace(/[^A-Z0-9 ]/g,' ')
    .replace(/\s+/g,' ')
    .trim()
}

/* Tinggi: minimal 2 digit sebelum desimal (desimal opsional) */
function heightRawHasMin2Digits(raw) {
  const s = String(raw ?? '').trim()
    .replace(/\s+/g, '')
    .replace(/cm$/i, '')
  if (!s) return false
  if (/^\d{2,}$/.test(s)) return true
  const m = s.match(/^(\d+)[.,]\d+$/)
  return !!(m && m[1] && m[1].length >= 2)
}

/* ====== Start!E6/H5 ====== */
function readCellSmart(ws, addr) {
  const c = ws?.[addr]
  if (c && (c.v != null || c.w != null)) return c.v ?? c.w ?? ''
  const merges = ws?.['!merges'] || []
  const tgt = XLSX.utils.decode_cell(addr)
  for (const m of merges) {
    if (tgt.r >= m.s.r && tgt.r <= m.e.r && tgt.c >= m.s.c && tgt.c <= m.e.c) {
      const tl = XLSX.utils.encode_cell(m.s)
      const mc = ws[tl]
      if (mc && (mc.v != null || mc.w != null)) return mc.v ?? mc.w ?? ''
    }
  }
  return ''
}
function parseMonthCell(val) {
  if (val == null || val === '') return null
  const n = parseInt(String(val).trim(), 10)
  if (Number.isFinite(n) && n>=1 && n<=12) return n
  const s = String(val).trim().toUpperCase()
  return byName[s]?.n ?? null
}
function parseYearCell(val) {
  if (val == null || val === '') return null
  let y = parseInt(String(val).trim(), 10)
  if (!Number.isFinite(y)) return null
  if (y < 100) y = 2000 + y
  if (y < 1900 || y > 2100) return null
  return y
}
function readStartMonthYear(ws) {
  const rawMonth = readCellSmart(ws, 'E6')
  const rawYear  = readCellSmart(ws, 'H5')
  let month = parseMonthCell(rawMonth)
  let year  = parseYearCell(rawYear)
  if (!month || !year) {
    const arr = XLSX.utils.sheet_to_json(ws, { header:1, defval:'', raw:false })
    for (const row of arr) {
      const cells = row.map(c => String(c||'').trim().toUpperCase())
      for (let c=0;c<cells.length;c++) {
        const v = cells[c]
        if (/BULAN/.test(v) && !month) {
          const cand = (cells[c+1] || cells[c+2] || '')
          const n = parseInt(cand,10)
          if (Number.isFinite(n) && n>=1 && n<=12) month = n
          else if (byName[cand]) month = byName[cand].n
        }
        if (/TAHUN/.test(v) && !year) {
          let yy = parseInt((cells[c+1] || cells[c+2] || ''), 10)
          if (Number.isFinite(yy)) {
            if (yy < 100) yy = 2000 + yy
            if (yy >= 1900 && yy <= 2100) year = yy
          }
        }
      }
    }
  }
  return { month, year }
}

/* ====== Header/kolom P1 ====== */
function detectHeaderStart(arr) {
  const maxScan = Math.min(25, arr.length)
  for (let i=0; i<maxScan; i++) {
    const row = (arr[i] || []).map(x => String(x||'').toUpperCase())
    if (row.some(x => x.includes('NIK'))) return i
  }
  return 0
}
function forwardFillRow(row, width) {
  const out = new Array(width).fill('')
  let carry = ''
  for (let j=0; j<width; j++) {
    const v = String(row[j] ?? '').trim()
    if (v) carry = v
    out[j] = carry
  }
  return out
}
function buildCompositeHeaderFromArr(arr, startRow, headerRows = 3) {
  const rows = []
  let width = 0
  for (let r=0; r<headerRows; r++) {
    const rr = arr[startRow + r] || []
    rows.push(rr)
    width = Math.max(width, rr.length)
  }
  const filled = rows.map(r => forwardFillRow(r, width))
  const headers = new Array(width).fill('').map((_, j) => {
    const parts = [filled[0][j], filled[1][j], filled[2][j]]
      .map(s => String(s||'').trim()).filter(Boolean)
    return parts.join(' ').replace(/\s+/g,' ').toUpperCase()
  })
  return { headers, bodyStart: startRow + headerRows }
}
function pickIndex(headers, tests) {
  const preds = tests.map(t => {
    if (typeof t === 'string')     return (h) => h.includes(t)
    if (t instanceof RegExp)       return (h) => t.test(h)
    if (typeof t === 'function')   return t
    return () => false
  })
  return headers.findIndex(h => preds.some(f => f(h)))
}
function findColumns(headers, monthNum) {
  const M = byNum[monthNum]
  const monthTokens = M.syn.map(s => s.toUpperCase())
  const hasMonth = (h) => monthTokens.some(tok => new RegExp(`\\b${tok}\\b`).test(h))
  const isWeight = (h) => /(BB|BERAT)\b/.test(h) && !/\bUMUR\b/.test(h)
  const isHeight = (h) => /(TB|TINGGI|PB|PANJANG)\b/.test(h) && !/\bUMUR\b/.test(h)

  let idxNik = pickIndex(headers, ['NIK'])
  let idxBB = -1, idxTB = -1
  headers.forEach((h,i) => {
    if (idxBB === -1 && hasMonth(h) && isWeight(h)) idxBB = i
    if (idxTB === -1 && hasMonth(h) && isHeight(h)) idxTB = i
  })
  if (idxBB === -1 || idxTB === -1) {
    headers.forEach((h, i) => {
      if (!hasMonth(h)) return
      for (let j=Math.max(0,i-3); j<=Math.min(headers.length-1,i+3); j++) {
        const hh = headers[j]
        if (idxBB === -1 && isWeight(hh)) idxBB = j
        if (idxTB === -1 && isHeight(hh)) idxTB = j
      }
    })
  }

  const idxNama    = pickIndex(headers, [/NAMA\s+ANAK/, (h)=>h.includes('NAMA') && !h.includes('ORTU') && !h.includes('POSYANDU')])
  const idxAnakKe  = pickIndex(headers, [/ANAK\s*KE/])
  const idxNoKK    = pickIndex(headers, [/NO(\.|)?\s*KK|NOMOR\s*KK/])
  const idxJK      = pickIndex(headers, [/\bL\s*\/\s*P\b/, 'JENIS KELAMIN','JK'])
  const idxRT      = pickIndex(headers, [/^RT\b/])
  const idxRW      = pickIndex(headers, [/^RW\b/])
  const idxNamaOrtu= pickIndex(headers, ['NAMA ORTU','NAMA ORANG TUA','NAMA ORANG  TUA','NAMA ORTU/WALI','NAMA WALI'])
  const idxNikOrtu = pickIndex(headers, [
    'NIK ORTU','NIK ORANG TUA','NIK ORTU/WALI','NIK WALI','NIK AYAH','NIK IBU',
    /\bNIK\s*(AYAH|IBU)\b/
  ])
  const idxHpOrtu  = pickIndex(headers, [
    'HP ORTU','NO HP','NO. HP','NO HP ORTU','NO HP ORANG TUA','HP ORANG TUA',
    'TELP ORANG TUA','NO TELP','NO TELEPON','NO. TELEPON','WHATSAPP ORTU','WA ORTU'
  ])
  const idxPosy    = pickIndex(headers, ['NAMA POSYANDU','POSYANDU'])

  const idxUsiaHamil = pickIndex(headers, [
    'USIA HAMIL','UMUR HAMIL','USIA KEHAMILAN','UMUR KEHAMILAN',
    /\bUK\b.*(MINGGU|MG|WEEK)/, /\bUSIA\s*KEHAMILAN\b/, /\bUK\s*\(.*\)\b/
  ])
  const idxBBLahir   = pickIndex(headers, [/BB\s*LAHIR|BERAT\s*LAHIR/])
  const idxPBLahir   = pickIndex(headers, [/PB\s*LAHIR|PANJANG\s*LAHIR|TINGGI\s*LAHIR/])
  const idxLKLahir   = pickIndex(headers, [
    /LINGKAR\s*(KEPALA|KPL)\s*LAHIR/, /\bLK\s*LAHIR\b/,
    /LINGKAR\s*KEP\.*\s*LAHIR/, /LING\.*\s*KEP\.*\s*LAHIR/
  ])
  const idxKIA       = pickIndex(headers, [/KIA\b|BUKU\s*KIA/])
  const idxKIABK     = pickIndex(headers, [
    /KIA\s*BAYI\s*(KECIL|KCL)/, /\bBBLR\b/, /KIA\s*BBLR/,
    /BAYI\s*BERAT\s*LAHIR\s*RENDAH/
  ])
  const idxIMD       = pickIndex(headers, [/^IMD\b|INISIASI\s+MENYUSUI\s+DINI/])

  const idxTgl = pickIndex(headers, [(h)=>h.includes('TGL LAHIR') && h.includes('TGL')])
  const idxBln = pickIndex(headers, [(h)=>h.includes('TGL LAHIR') && h.includes('BLN')])
  const idxThn = pickIndex(headers, [(h)=>h.includes('TGL LAHIR') && h.includes('THN')])

  // Vitamin A (gabungan header)
  const idxVitBiru  = pickIndex(headers, [/\bVIT\.?A\b.*\bBIRU\b/, (h)=>h.includes('VIT.A BIRU')])
  const idxVitMerah = pickIndex(headers, [/\bVIT\.?A\b.*\bMERAH\b/, (h)=>h.includes('VIT.A MERAH')])

  // ASI 0..6
  const idxASI = Array.from({length:7}, (_,i)=> pickIndex(headers, [
    (h)=> /\bASI\b/.test(h) && new RegExp(`\\b${i}\\b`).test(h),
    new RegExp(`ASI\\s*(BULAN\\s*)?${i}\\b`),
    (h)=> /\bASI\b/.test(h) && new RegExp(`\\bBULAN\\s*${i}\\b`).test(h)
  ]))

  // Kelas Ibu Balita
  const idxKelasIbu = pickIndex(headers, [/KELAS\s*IBU\s*BALITA/, /KELAS\s*IBU\b/])

  return {
    idxNik, idxBB, idxTB,
    idxNama, idxAnakKe, idxNoKK, idxJK, idxRT, idxRW,
    idxNamaOrtu, idxNikOrtu, idxHpOrtu, idxPosy,
    idxUsiaHamil, idxBBLahir, idxPBLahir, idxLKLahir, idxKIA, idxKIABK, idxIMD,
    idxTgl, idxBln, idxThn,
    idxVitBiru, idxVitMerah,
    idxASI, idxKelasIbu
  }
}

/* ====== DOB dari 3 kolom ====== */
function parseDobFromCols(d, m, y) {
  if (y==null || y==='') return null
  let yy = parseInt(String(y).trim(), 10)
  if (!Number.isFinite(yy)) return null
  if (yy < 100) yy = 2000 + yy

  let mm
  if (m!=null && m!=='') {
    const ms = String(m).trim().toUpperCase()
    const mi = parseInt(ms, 10)
    if (Number.isFinite(mi)) mm = mi
    else mm = byName[ms]?.n
  }
  if (!mm || mm<1 || mm>12) return null

  let dd = 1
  if (d!=null && d!=='') {
    const di = parseInt(String(d).trim(), 10)
    if (Number.isFinite(di) && di>=1 && di<=31) dd = di
  }
  const MM = String(mm).padStart(2,'0')
  const DD = String(dd).padStart(2,'0')
  return `${yy}-${MM}-${DD}`
}

/* ====== Map posyandu text -> id ====== */
function normalizePosText(raw) {
  const s = String(raw||'').toUpperCase().replace(/[-–.,]/g,' ').replace(/\s+/g,' ').trim()
  return s
}
function resolvePosyanduIdFromExcel(val) {
  const s = normalizePosText(val)
  if (!s) return null
  const desaMatch = s.match(/DESA\s+([A-Z0-9 ]+)/)
  const namaMatch = s.match(/POSY(?:ANDU|)\s+([A-Z0-9 ]+)/)
  const desa = (desaMatch?.[1]||'').trim()
  const pos  = (namaMatch?.[1]||'').trim()
  const num  = (pos.match(/\d+/)?.[0]) || ''

  const cands = posList.value.filter(p =>
    (!desa || p.desa.startsWith(desa)) &&
    (!pos  || p.nama.startsWith(pos.replace(/\d+/g,'').trim()))
  )
  if (cands.length === 1) return cands[0].id
  if (num) {
    const exactNum = cands.find(p => p.num === num)
    if (exactNum) return exactNum.id
  }
  const lab = s.replace(/\s+/g,' ')
  const eq = cands.find(p => lab.includes(p.label))
  return eq?.id || null
}

/* ====== API Anak (cek eksistensi) ====== */
async function getAnakById(id) {
  if (!id) return null

  try {
    const res = await api.get(`/anak/${id}`, {
      headers: { Accept: 'application/json' },
    })

    return res?.data?.data ?? res?.data ?? null
  } catch (err) {
    console.error('getAnakById error:', err)
    return null
  }
}

async function getAnakByNik(nik) {
  try {
    const r = await api.get(`/anak/nik/${nik}`, { headers:{ Accept:'application/json' } })
    return r?.data?.data ?? r?.data ?? null
  } catch {
    try {
      const r2 = await api.get('/anak', { headers:{ Accept:'application/json' } })
      const list =
        Array.isArray(r2.data) ? r2.data :
        Array.isArray(r2?.data?.data) ? r2.data.data :
        Array.isArray(r2?.data?.data?.data) ? r2.data.data.data : []
      return list.find(x => String(x?.nik||'') === String(nik)) || null
    } catch { return null }
  }
}

function toYMD(v) {
  if (!v) return null
  const d = new Date(v)
  if (Number.isNaN(d.getTime())) return null
  const y = d.getFullYear()
  const m = String(d.getMonth()+1).padStart(2,'0')
  const day = String(d.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}

async function existsPengukuran(nik, tanggal_ukur) {
  const target = toYMD(tanggal_ukur)
  if (!nik || !target) return false

  try {
      const res = await api.get(`/anak-pengukuran?nik_anak=${encodeURIComponent(nik)}`, {
      headers: { Accept: 'application/json' }
    })
    const d = res?.data
    const arr =
      Array.isArray(d) ? d :
      Array.isArray(d?.data) ? d.data :
      Array.isArray(d?.data?.data) ? d.data.data : []

    return Array.isArray(arr) && arr.some(x =>
      String(x?.nik_anak || x?.nik) === String(nik) &&
      toYMD(x?.tanggal_ukur || x?.tanggal || x?.tanggalUkur) === target
    )
  } catch {
    return false
  }
}

/* ====== UTIL EXPORT ====== */
function formatAlamat(desa, posy) {
  const d = String(desa ?? '').trim()
  const p = String(posy ?? '').trim().toUpperCase()
  if (!d && !p) return ''
  if (d && p) return `Desa ${d}-Posy. ${p}`
  if (d) return `Desa ${d}`
  return `Posy. ${p}`
}
function autoFitColumns(ws, minWidth = 10, maxWidth = 60) {
  const headerRow = ws.getRow(1)
  const headerNameByCol = {}
  headerRow.eachCell((cell, colNumber) => {
    headerNameByCol[colNumber] = String(cell.value ?? '').trim().toLowerCase()
  })
  for (let c = 1; c <= ws.columnCount; c++) {
    let maxLen = 0
    ws.eachRow({ includeEmpty: true }, (row) => {
      const cell = row.getCell(c)
      let s = cell && cell.text != null ? String(cell.text) : ''
      if (!s) {
        const v = cell?.value
        if (v == null) s = ''
        else if (typeof v === 'string') s = v
        else if (typeof v === 'number') s = String(v)
        else if (v instanceof Date) s = v.toISOString().slice(0,10)
        else if (typeof v === 'object') {
          if (v?.text) s = String(v.text)
          else if (Array.isArray(v?.richText)) s = v.richText.map(r => r.text || '').join('')
          else if (v?.result != null) s = String(v.result)
          else s = ''
        } else s = String(v)
      }
      const longest = Math.max(0, ...s.replace(/\r/g,'').split('\n').map(t => t.length))
      if (longest > maxLen) maxLen = longest
    })
    const headerKey = headerNameByCol[c] || ''
    const minForThis = headerKey === 'nama_anak' ? Math.max(minWidth, 22) : minWidth
    ws.getColumn(c).width = Math.min(Math.max(maxLen + 2, minForThis), maxWidth)
  }
}
function kodeSingkatDesa(desa) {
  const d = String(desa||'').trim().toUpperCase()
  if (!d) return 'UNK'
  const norm = d.replace(/[^A-Z0-9 ]/g,' ').replace(/\s+/g,' ').trim()
  const overrides = { 'ORO-ORO OMBO':'ORO','NGAGLIK':'NGK','SUMBEREJO':'SBJ','PESANGGRAHAN':'PSG','SONGGOKERTO':'SGK' }
  if (overrides[norm]) return overrides[norm]
  return norm.replace(/[^A-Z0-9]/g,'').slice(0,3) || 'UNK'
}
function buildKodeASP(desaFromStart) { return `ID ${kodeSingkatDesa(desaFromStart)}` }

/* ====== NIK SMART (≥10 digit; selain itu BELUM ADA) ====== */
function parseNikSmart(v) {
  const raw = String(v ?? '').trim().toUpperCase()
  const d = digits(raw)
  const tooShort = d.length < 10
  const missing =
    !d || tooShort ||
    /(^|[^A-Z])BELUM\s*ADA([^A-Z]|$)/.test(raw) ||
    /^N\/?A$/.test(raw) ||
    /^TIDAK\s*ADA$/.test(raw)
  return { nik: missing ? null : d, display: missing ? '-/(BELUM ADA)' : d }
}

/* ====== DETEKSI BARIS BERMAKNA (ANTI SHEET KOSONG) ====== */
function isTrivialPlaceholder(v) {
  const s = String(v ?? '').trim().toUpperCase()
  if (!s) return true
  const stripped = s.replace(/[-/_.\s]/g, '')
  if (!stripped) return true
  if (['N/A','NA','TIDAKADA','KOSONG','NULL','NONE','-','BELUMADA'].includes(stripped)) return true
  if (/^0+$/.test(stripped)) return true
  return false
}
function hasMeaningfulString(v) { return !isTrivialPlaceholder(v) }
function hasPositiveNumber(v) {
  const n = toFloat(v); return Number.isFinite(n) && n > 0
}
function hasDigitsAtLeast(v, n) { return digits(v).length >= n }

function isMeaningfulRow(r, idx) {
  // identitas wajib: nik kuat / nama / tgl lahir
  const nikDigits = (idx.idxNik !== -1) ? digits(r[idx.idxNik]) : ''
  const hasNikStrong = nikDigits.length >= 10

  const nama = (idx.idxNama !== -1) ? String(r[idx.idxNama]||'').trim() : ''
  const hasNama = !isTrivialPlaceholder(nama)

  let dob = null
  if (idx.idxTgl !== -1 || idx.idxBln !== -1 || idx.idxThn !== -1) {
    dob = parseDobFromCols(
      idx.idxTgl !== -1 ? r[idx.idxTgl] : '',
      idx.idxBln !== -1 ? r[idx.idxBln] : '',
      idx.idxThn !== -1 ? r[idx.idxThn] : ''
    )
  }
  const hasDob = !!dob

  const hasIdent = hasNikStrong || hasNama || hasDob
  if (!hasIdent) return false

  // Tetap buang baris placeholder (—, 0, N/A, dsb.)
  const nonPlaceholderCount = (Array.isArray(r) ? r : [])
    .map(v => String(v ?? '').replace(/\u00A0/g,' ').trim())
    .filter(v => !isTrivialPlaceholder(v))
    .length

  return nonPlaceholderCount > 0
}

/* ====== HANDLE FILES (Start + P1..P10) ====== */
async function handleFiles(ev) {
  const f = ev.target.files?.[0]
  if (!f) return
  if (!/\.xlsx?$/i.test(f.name)) { await Swal.fire('Format tidak didukung','Pilih .xls/.xlsx','warning'); return }

  parsing.value = true
  sheets.value = []
  Object.assign(startInfo, { month:null, year:null, label:'-' })

  try {
    const buf = await f.arrayBuffer()
    const wb = XLSX.read(buf, { type:'array' })

    // START: periode & desa
    const wsStart = wb.Sheets['Start'] || wb.Sheets[wb.SheetNames.find(n => n.toLowerCase().includes('start'))]
    if (!wsStart) throw new Error('Sheet "Start" tidak ditemukan')
    const desaCell = readCellSmart(wsStart, 'E5')
    stateDesaStart.value = String(desaCell ?? '').trim().toUpperCase()

    const st = readStartMonthYear(wsStart)
    if (!st.month || !st.year) throw new Error('BULAN/TAHUN tidak terbaca (Start!E6/H5)')
    startInfo.month = st.month
    startInfo.year  = st.year
    startInfo.label = `${byNum[st.month].long} ${st.year}`

    // --- Kumpulkan nama P1..P10 (selalu buat placeholder sheet) ---
    const plan = Array.from({length:10}, (_,i)=>({ idx:i+1, name:`P${i+1}` }))

    const collected = []
    for (const w of plan) {
      const wsName =
        wb.SheetNames.find(n => n.toUpperCase().trim() === `P${w.idx}`) ||
        wb.SheetNames.find(n => /\bP\s*\d+\b/i.test(n) && n.replace(/\s+/g,'').toUpperCase() === `P${w.idx}`) ||
        wb.SheetNames.find(n => n.toUpperCase().includes(`P${w.idx}`))

      const ws = wsName ? wb.Sheets[wsName] : null
      if (!ws) {
        collected.push({
          name: `P${w.idx}`,
          rows: [],
          preview: [],
          checking: false,
          checkTotal: 0,
          checkDone: 0,
          tempTanggalUkur: '',
          overrideTanggalUkur: '',
          _sheetMissing: true,
          _sheetEmpty: false,
          selectAll: false,
          importing: false,
          importTotal: 0,
          importDone: 0,
        })
        continue
      }

      let arr = XLSX.utils.sheet_to_json(ws, { header:1, defval:'', raw:false })
      if (!arr.length) {
        collected.push({
          name: `P${w.idx}`,
          rows: [],
          preview: [],
          checking: false,
          checkTotal: 0,
          checkDone: 0,
          tempTanggalUkur: '',
          overrideTanggalUkur: '',
          _sheetMissing: false,
          _sheetEmpty: true,
          selectAll: false,
          importing: false,
          importTotal: 0,
          importDone: 0,
        })
        continue
      }

      const start = detectHeaderStart(arr)
      const { headers, bodyStart } = buildCompositeHeaderFromArr(arr, start, 3)
      const idx = findColumns(headers, startInfo.month)
      if (idx.idxNik === -1) {
        collected.push({
          name: `P${w.idx}`,
          rows: [],
          preview: [],
          checking: false,
          checkTotal: 0,
          checkDone: 0,
          tempTanggalUkur: '',
          overrideTanggalUkur: '',
          _sheetMissing: false,
          _sheetEmpty: true,
          selectAll: false,
          importing: false,
          importTotal: 0,
          importDone: 0,
        })
        continue
      }

      let body = arr.slice(bodyStart)

      // Buang baris unit "kg/cm" jika baris pertama hanya satuan dan tidak ada identitas
      if (body.length) {
        const first = body[0] || []
        const nikCell  = idx.idxNik   !== -1 ? String(first[idx.idxNik] ?? '') : ''
        const namaCell = idx.idxNama  !== -1 ? String(first[idx.idxNama] ?? '') : ''
        const bbCell   = idx.idxBB    !== -1 ? String(first[idx.idxBB]   ?? '') : ''
        const tbCell   = idx.idxTB    !== -1 ? String(first[idx.idxTB]   ?? '') : ''
        const noIdent = !digits(nikCell) && !namaCell.trim()
        const looksLikeUnit = /\bkg\b/i.test(bbCell) || /\bcm\b/i.test(tbCell)
        if (noIdent && looksLikeUnit) body = body.slice(1)
      }

      const out = []
      for (const r of body) {
        if (!Array.isArray(r)) continue

        const joined = r.map(x => String(x ?? '').replace(/\u00A0/g,' ').trim()).join('')
        if (!joined) continue
        if (!isMeaningfulRow(r, idx)) continue

        const nikObj = parseNikSmart(r[idx.idxNik])
        const bb = idx.idxBB !== -1 ? toFloat(r[idx.idxBB]) : null
        const tb = idx.idxTB !== -1 ? toFloat(r[idx.idxTB]) : null

        // Vitamin & ASI & Kelas Ibu
        const rawVitBiru  = (idx.idxVitBiru  !== -1 ? r[idx.idxVitBiru]  : '')
        const rawVitMerah = (idx.idxVitMerah !== -1 ? r[idx.idxVitMerah] : '')
        const asiFlags = Array.from({length:7}, (_,i)=> (idx.idxASI?.[i] ?? -1) !== -1 ? toBool(r[idx.idxASI[i]]) : false)
        const kelasIbu = (idx.idxKelasIbu !== -1) ? toBool(r[idx.idxKelasIbu]) : false

        const nama_anak = idx.idxNama !== -1 ? String(r[idx.idxNama]||'').trim() : ''
        const tgl_lahir = (idx.idxTgl !== -1 || idx.idxBln !== -1 || idx.idxThn !== -1)
          ? parseDobFromCols(r[idx.idxTgl], r[idx.idxBln], r[idx.idxThn]) : null
        const jenis_kelamin = idx.idxJK !== -1 ? parseJK(r[idx.idxJK]) : ''
        const nomor_KK = idx.idxNoKK !== -1 ? String(r[idx.idxNoKK]||'').trim() : ''
        const anak_ke = idx.idxAnakKe !== -1 ? (r[idx.idxAnakKe] || '') : ''
        const usia_hamil = idx.idxUsiaHamil !== -1 ? (r[idx.idxUsiaHamil] || '') : ''
        const berat_lahir = idx.idxBBLahir !== -1 ? toFloat(r[idx.idxBBLahir]) : ''
        const panjang_lahir = idx.idxPBLahir !== -1 ? toFloat(r[idx.idxPBLahir]) : ''
        const lingkar_kepala_lahir = idx.idxLKLahir !== -1 ? toFloat(r[idx.idxLKLahir]) : ''
        const kia = idx.idxKIA !== -1 ? toBool(r[idx.idxKIA]) : false
        const kia_bayi_kecil = idx.idxKIABK !== -1 ? toBool(r[idx.idxKIABK]) : false
        const imd = idx.idxIMD !== -1 ? toBool(r[idx.idxIMD]) : false
        const nama_ortu = idx.idxNamaOrtu !== -1 ? String(r[idx.idxNamaOrtu]||'').trim() : ''
        const nik_ortu = idx.idxNikOrtu !== -1 ? String(r[idx.idxNikOrtu]||'').toString().trim() : ''
        const hp_ortu  = idx.idxHpOrtu  !== -1 ? String(r[idx.idxHpOrtu]||'').toString().trim() : ''
        const rt = idx.idxRT !== -1 ? String(r[idx.idxRT]||'').toString().trim() : ''
        const rw = idx.idxRW !== -1 ? String(r[idx.idxRW]||'').toString().trim() : ''
        const posyText = idx.idxPosy !== -1 ? r[idx.idxPosy] : ''
        const posyandu_id = resolvePosyanduIdFromExcel(posyText)

        out.push({
          nik: nikObj.nik,
          nik_display: nikObj.display,
          bb, tb,
          __tb_source: r[idx.idxTB],
          __vit_raw: { biru: rawVitBiru, merah: rawVitMerah },
          __asi: asiFlags,
          __kelas_ibu: kelasIbu,
          child: {
            nik: nikObj.nik,
            anak_ke, tgl_lahir, jenis_kelamin, nomor_KK, nama_anak,
            usia_hamil, berat_lahir, panjang_lahir, lingkar_kepala_lahir,
            kia, kia_bayi_kecil, imd, nama_ortu, nik_ortu, hp_ortu,
            __tb_source: r[idx.idxTB],
            posyandu_id, rt, rw,
            posyText
          }
        })
      }

      collected.push({
        name: `P${w.idx}`,
        rows: out,
        preview: [],
        checking: false,
        checkTotal: 0,
        checkDone: 0,
        tempTanggalUkur: '',
        overrideTanggalUkur: '',
        _sheetMissing: false,
        _sheetEmpty: out.length === 0,
        selectAll: false,
        importing: false,
        importTotal: 0,
        importDone: 0,
      })
    }

    sheets.value = collected
    await Swal.fire('Berhasil', `Periode: <b>${startInfo.label}</b><br>Sheet aktif: <b>${collected.length}</b>`, 'success')
  } catch (err) {
    console.error(err)
    await Swal.fire('Gagal membaca file', err?.message || 'Kesalahan tidak diketahui', 'error')
  } finally { parsing.value = false }
}

/* ====== PREVIEW per SHEET ====== */
async function getPosyById(id) {
  if (!id) return null
  return posList.value.find(p => String(p.id) === String(id)) || null
}
async function buildPreviewFor(sheetIdx, onTickGlobal = null) {
  const S = sheets.value[sheetIdx]
  if (!S || !S.rows.length || S._sheetMissing || S._sheetEmpty) {
    await Swal.fire('Tidak ada data', `Sheet ${S?.name || ''} tidak punya kandidat.`, 'info')
    return
  }
  S.checking = true

  const nikNamesRaw = new Map();
  for (const r of (S.rows || [])) {
    if (!r?.nik) continue
    const nm = String(r?.child?.nama_anak || '').trim()
    if (!nikNamesRaw.has(r.nik)) nikNamesRaw.set(r.nik, new Set())
    if (nm) nikNamesRaw.get(r.nik).add(nm)
  }
  const hasNameConflictInSheet = (nik, currentRawName='') => {
    const set = nikNamesRaw.get(nik)
    if (!set || set.size <= 1) return false
    if (!currentRawName) return true
    const cur = normName(currentRawName)
    return Array.from(set).some(n => normName(n) !== cur)
  }

  S.preview = []
  S.checkTotal = S.rows.length
  S.checkDone = 0

  try {
    const list = []

    for (let ri = 0; ri < S.rows.length; ri++) {
      const r = S.rows[ri]

      const anak = r.nik ? await getAnakByNik(r.nik) : null
      const activeRefDate =
        (sheets.value[sheetIdx]?.overrideTanggalUkur) ||
        ymdFromMonth(startInfo.year, startInfo.month)

      const dob  = (anak?.tgl_lahir || r.child.tgl_lahir || null)
      const umur = dob ? diffMonths(dob, activeRefDate) : null

      // Posyandu
      const knownPosId = anak?.posyandu_id || anak?.posyandu?.id || r.child.posyandu_id || null
      const posyObj = await getPosyById(knownPosId)

      // --- flag & peringatan ---
      const missingBB = !(r.bb != null && r.bb !== '')
      const missingTB = !(r.tb != null && r.tb !== '')
      const notHadir  = (missingBB || missingTB)
      const ageOver   = (umur != null && umur > AGE_SKIP_MONTHS)

      const warns = []
      if (!missingTB) {
        const rawSource =
          (typeof r.__tb_source !== 'undefined') ? r.__tb_source :
          r.child?.__tb_source
        const toCheck = rawSource ?? r.tb ?? ''
        if (!heightRawHasMin2Digits(toCheck)) {
          warns.push('⚠ Format TB (min. 2 digit angka; desimal opsional)')
        }
      }

      let vitDisplay = ''
      let vitCode = null   // 'biru' | 'merah' | null
      let vitBiru = false
      let vitMerah = false

      const mRef = new Date(activeRefDate).getMonth() + 1
      const isVitMonth = (mRef === 2 || mRef === 8)

      if (isVitMonth) {
        const biruAda  = toVitFlag(r.__vit_raw?.biru)
        const merahAda = toVitFlag(r.__vit_raw?.merah)

        if (biruAda && merahAda) {
          const pilihBiru = (umur == null) ? true : (umur < 24)
          vitDisplay = pilihBiru ? 'Biru' : 'Merah'
          vitCode    = pilihBiru ? 'Biru' : 'Merah'
          vitBiru    =  pilihBiru
          vitMerah   = !pilihBiru
        } else if (biruAda) {
          vitDisplay = 'Biru'
          vitCode    = 'Biru'
          vitBiru    = true
        } else if (merahAda) {
          vitDisplay = 'Merah'
          vitCode    = 'Merah'
          vitMerah   = true
        }
      }

      // ASI tags
      const asiTags = (r.__asi || []).reduce((acc, on, idx) => (on ? acc.concat(idx) : acc), [])

      // Kelas Ibu
      const kelasIbu = !!r.__kelas_ibu

      // ===== Cek konflik nama
      const excelNameRaw = String(r?.child?.nama_anak || '').trim()
      const conflictInSheet = r.nik ? hasNameConflictInSheet(r.nik, excelNameRaw) : false
      const systemName = anak?.nama_anak || ''
      const dbMismatch = r.nik && anak && normName(systemName) && normName(systemName) !== normName(excelNameRaw)
      if (conflictInSheet) {
        const listFileNames = Array.from(nikNamesRaw.get(r.nik) || [])
        warns.push(`NIK sama, nama di file: ${listFileNames.join(' / ')}`)
      }
      if (dbMismatch) {
        warns.push(`Nama di sistem: ${systemName} · Di Excel: ${excelNameRaw || '-'}`)
      }

      let already = false
      if (!ageOver && !notHadir && anak) {
        try { already = await existsPengukuran(r.nik, activeRefDate) } catch {}
      }

      let status = 'Siap diimport'
      if (ageOver) {
        status = 'Skip (umur > 60 bln)'
      } else if (notHadir) {
        status = 'Skip (Tidak hadir posyandu: BB/TB kosong)'
      } else if (!r.nik) {
        status = 'NIK belum ada'
      } else if (conflictInSheet) {
        status = 'Perhatian: NIK duplikat (nama beda di file)'
      } else if (!anak) {
        status = 'Anak belum terdaftar'
      } else if (dbMismatch) {
        status = 'Perhatian: Nama berbeda dengan data sistem'
      } else if (already) {
        status = 'Skip (sudah ada pengukuran tanggal ini)'
      }

      // Cara ukur dari umur
      let caraUkur = ''
      if (umur != null) caraUkur = (umur >= 24) ? 'Berdiri' : 'Terlentang'

      // eligible = memenuhi syarat impor (nik ada, anak ada, hadir, umur <= limit, belum ada pengukuran)
      const eligible =
        !!r.nik &&
        !!anak &&
        !notHadir &&
        !(umur != null && umur > AGE_SKIP_MONTHS) &&
        !already

      list.push({
        nik: r.nik,
        nik_display: r.nik_display,
        nama: anak?.nama_anak || r.child.nama_anak || '-',
        tgl_lahir: dob,
        umur_bulan: umur,
        posy: posyObj?.nama || r.child.posyText || '-',
        desa: posyObj?.desa || (stateDesaStart.value || '-') || '-',
        bb: r.bb ?? null,
        tb: r.tb ?? null,
        exists: !!anak,
        status,
        warns,
        cara_ukur: caraUkur || '-',
        vit_a_display: vitDisplay || '',
        vit_a: vitCode,
        vit_biru: vitBiru,
        vit_merah: vitMerah,
        asi: asiTags,
        kelas_ibu: kelasIbu,

        _eligible: eligible,
        _selected: eligible,
        _ord: ri,
        _ageOver: ageOver,
        _notHadir: notHadir,
        _nameConflict: conflictInSheet,
        _dbMismatch: dbMismatch,
        sheetIdx,
        child: r.child,
      })

      S.checkDone++
      if (typeof onTickGlobal === 'function') onTickGlobal()
      if (ri % 50 === 0) await new Promise(rz => setTimeout(rz, 0))
    }

    list.sort((a, b) => {
      if (a._ageOver !== b._ageOver) return a._ageOver ? 1 : -1
      if (a._notHadir !== b._notHadir) return a._notHadir ? 1 : -1
      return (a._ord ?? 0) - (b._ord ?? 0)
    })
    S.preview = list
  } finally {
    setTimeout(() => {
      S.checkTotal = 0
      S.checkDone = 0
    }, 1000)
    S.checking = false
  }
}

function missingOnlyOf(S) {
  return (S.preview || []).filter(p => !p.exists)
}

/* ====== CEK SEMUA SHEET ====== */
async function buildPreviewAll() {
  if (!sheets.value.length) {
    await Swal.fire('Tidak ada sheet', 'Upload file terlebih dahulu.', 'info')
    checkProgress.done = 0
    checkProgress.total = 0
    return
  }

  checkProgress.total = sheets.value.reduce((acc, s) => acc + (s.rows?.length || 0), 0)
  checkProgress.done = 0
  checkingAll.value = true

  try {
    const tick = () => { checkProgress.done++ }
    for (let i = 0; i < sheets.value.length; i++) {
      await buildPreviewFor(i, tick)
    }
    await Swal.fire('Selesai', `Cek ${sheets.value.length} sheet (${checkProgress.total} baris) selesai.`, 'success')
  } finally {
    checkingAll.value = false
  }
}

/* ====== EXPORT per SHEET (anak belum ada) ====== */
async function exportMissingToXLSBySheet(sheetIdx) {
  const S = sheets.value[sheetIdx]
  if (!S) return
  const data = missingOnlyOf(S)
  if (!data.length) {
    await Swal.fire('Tidak ada', `Sheet ${S.name}: Semua anak sudah terdaftar — tidak ada yang diekspor.`, 'info')
    return
  }

  const toYa = (v) => v ? 'Ya' : ''

  const header = [
    'No','anak_ke','tgl_lahir','jenis_kelamin','nomor_KK','NIK','nama_anak',
    'usia_hamil','berat_lahir','panjang_lahir','lingkar_kepala_lahir',
    'kia','kia_bayi_kecil','imd','nama_ortu','nik_ortu','hp_ortu','alamat','rt','rw'
  ]

  const rows = data.map((p, i) => {
    const c = p.child || {}
    const desa = stateDesaStart.value || ''
    const posy = c.posyText || ''
    const alamat = formatAlamat(desa, posy)
    return [
      i + 1,
      c.anak_ke ?? '',
      c.tgl_lahir ?? '',
      c.jenis_kelamin ?? '',
      c.nomor_KK ?? '',
      p.nik_display ?? '-/(BELUM ADA)',
      c.nama_anak ?? p.nama ?? '',
      c.usia_hamil ?? '',
      c.berat_lahir ?? '',
      c.panjang_lahir ?? '',
      c.lingkar_kepala_lahir ?? '',
      toYa(c.kia),
      toYa(c.kia_bayi_kecil),
      toYa(c.imd),
      c.nama_ortu ?? '',
      c.nik_ortu ?? '',
      c.hp_ortu ?? '',
      alamat,
      c.rt ?? '',
      c.rw ?? ''
    ]
  })

  const wb = new ExcelJS.Workbook()
  const ws = wb.addWorksheet(`Missing_${S.name}`)
  ws.addRow(header)
  const headerRow = ws.getRow(1)
  headerRow.eachCell((cell) => {
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFF00' } }
    cell.alignment = { vertical: 'middle', horizontal: 'left' }
    cell.border = { top:{style:'thin'}, left:{style:'thin'}, bottom:{style:'thin'}, right:{style:'thin'} }
  })
  rows.forEach(r => ws.addRow(r))
  ws.eachRow({ includeEmpty: false }, (row, rowNumber) => {
    if (rowNumber === 1) return
    row.eachCell((cell) => {
      cell.border = { top:{style:'thin'}, left:{style:'thin'}, bottom:{style:'thin'}, right:{style:'thin'} }
      cell.alignment = { vertical: 'middle', horizontal: 'left' }
    })
  })
  autoFitColumns(ws)

  const kodeFile = buildKodeASP(stateDesaStart.value)
  const fname = `${kodeFile}_${S.name}.xlsx`
  const buf = await wb.xlsx.writeBuffer()
  saveAs(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), fname)
  await Swal.fire('Berhasil', `Sheet <b>${S.name}</b>: ekspor <b>${rows.length}</b> baris ke <b>${fname}</b>.`, 'success')
}

/* ====== IMPORT PENGUKURAN (gabungan semua sheet yang sudah di-preview) ====== */
const validForImport = computed(() => {
  const all = []
  for (const S of sheets.value) {
    for (const p of (S.preview || [])) {
      if (p._selected) {
        all.push(p)
      }
    }
  }
  return all
})

async function resolveAnak({ nik, id }) {
  if (id) return await getAnakById(id)
  if (nik) return await getAnakByNik(nik)
  return null
}

async function applyTanggalSheet(si) {
  const S = sheets.value[si]
  if (!S) return
  const t = S.tempTanggalUkur
  if (!t) {
    await Swal.fire('Tanggal kosong', 'Pilih tanggal pengukuran dahulu.', 'warning')
    return
  }
  S.overrideTanggalUkur = t
  await Swal.fire('Terapkan', `Tanggal pengukuran di ${S.name} di-set ke <b>${t}</b>. Status akan disesuaikan.`, 'success')
  await buildPreviewFor(si)
}

async function doImport() {
  const hasAnyPreview = sheets.value.some(S => (S.preview || []).length)
  if (!hasAnyPreview) { await Swal.fire('Belum ada preview','Klik "Cek Data" pada sheet yang ingin diimport atau "Cek Semua Sheet".','info'); return }
  if (!validForImport.value.length) { await Swal.fire('Tidak ada yang bisa diimport','Pilih (centang) minimal 1 baris yang memenuhi syarat.', 'info'); return }

  const { isConfirmed } = await Swal.fire({
    icon:'question',
    title:'Konfirmasi Import',
    html:`Periode: <b>${byNum[startInfo.month].long} ${startInfo.year}</b><br>Dipilih: <b>${validForImport.value.length}</b> baris`,
    showCancelButton:true,
    confirmButtonText:'Mulai Import',
    cancelButtonText:'Batal'
  })
  if (!isConfirmed) return

  importing.value = true
  progress.total = validForImport.value.length
  progress.done = 0
  let created=0, skipped=0, failed=0
  const skippedInfo = []
  const errs = []
  const seenBatch = new Set()

  for (const p of validForImport.value) {
    try {
      const anak = await resolveAnak({ nik: p.nik, id: p.id })
      if (!anak) { skipped++; skippedInfo.push({ nik:p.nik, reason:'Anak tidak ditemukan di sistem' }); continue }

      const umur = p.umur_bulan
      if (umur != null && umur >= AGE_SKIP_MONTHS) { skipped++; skippedInfo.push({ nik:p.nik, reason:'Umur ≥ batas (60 bln)' }); continue }
      if (p._notHadir) { skipped++; skippedInfo.push({ nik:p.nik, reason:'Tidak hadir (BB/TB kosong)' }); continue }

      const tanggal_ukur =
        (sheets.value[p.sheetIdx]?.overrideTanggalUkur) ||
        ymdFromMonth(startInfo.year, startInfo.month)

      const key = `${p.nik}__${tanggal_ukur}`
      if (seenBatch.has(key)) { skipped++; skippedInfo.push({ nik:p.nik, reason:'Duplikat dalam batch (NIK+Tanggal sama)' }); continue }
      seenBatch.add(key)

      const already = await existsPengukuran(p.nik, tanggal_ukur)
      const posyandu_id = anak?.posyandu_id || anak?.posyandu?.id || p.child.posyandu_id || null
      const cara_ukur = (umur != null && umur < 24) ? 'Terlentang' : 'Berdiri'

      // Vit. A hanya untuk Feb/Ags (null di bulan lain)
      const isVitMonth = (() => { const d = new Date(tanggal_ukur); const m = d.getMonth()+1; return m === 2 || m === 8 })()

      const payload = {
        anak_id: anak.id,
        tanggal_ukur,
        posyandu_id,
        berat: p.bb ?? null,
        tinggi: p.tb ?? null,
        cara_ukur,
        vit_a: isVitMonth ? (p.vit_a || null) : null,
        asi_bulan_0: !!(p.asi?.includes?.(0) || p.asi?.[0]),
        asi_bulan_1: !!(p.asi?.includes?.(1) || p.asi?.[1]),
        asi_bulan_2: !!(p.asi?.includes?.(2) || p.asi?.[2]),
        asi_bulan_3: !!(p.asi?.includes?.(3) || p.asi?.[3]),
        asi_bulan_4: !!(p.asi?.includes?.(4) || p.asi?.[4]),
        asi_bulan_5: !!(p.asi?.includes?.(5) || p.asi?.[5]),
        asi_bulan_6: !!(p.asi?.includes?.(6) || p.asi?.[6]),
        kelas_ibu_balita: !!p.kelas_ibu,
      }

      if (already) {
        skipped++; skippedInfo.push({ nik:p.nik, reason:`Sudah ada pengukuran pada ${tanggal_ukur}` })
        continue
      }
      console.log(p);
      await api.post('/anak-pengukuran', payload, { headers:{Accept:'application/json'} })
      created++
    } catch (e) {
      failed++; errs.push({ nik: p.nik || p.nik_display, reason: e?.response?.data?.message || 'Gagal import' })
    } finally {
      progress.done++
    }
  }

  importing.value = false
  const skippedHtml = skippedInfo.length
    ? `<hr><div class="small"><b>Detail Skipped (${skippedInfo.length}):</b><br>${skippedInfo.slice(0,20).map(s=>`${s.nik}: ${s.reason}`).join('<br>')}${skippedInfo.length>20?'<br>…':''}</div>`
    : ''

  const html = `
    <div class="text-start">
      <div><b>Periode:</b> ${byNum[startInfo.month].long} ${startInfo.year}</div>
      <div><b>Diproses:</b> ${progress.total}</div>
      <hr>
      <div>✅ Created: <b>${created}</b></div>
      <div>❌ Failed: <b>${failed}</b></div>
      <div>⏭️ Skipped: <b>${skipped}</b> <span class="text-muted">(sudah ada / duplikat batch / usia ≥ limit / tidak hadir)</span></div>
      ${errs.length ? `<hr><div class="small"><b>Gagal:</b><br>${errs.slice(0,8).map(e=>`${e.nik}: ${e.reason}`).join('<br>')}${errs.length>8?'<br>…':''}</div>` : ''}
      ${skippedHtml}
    </div>`
  await Swal.fire({ icon: failed ? 'warning' : 'success', title:'Selesai', html })
  progress.done = 0
  progress.total = 0
}

async function doImportSheet(si) {
  const S = sheets.value[si]
  if (!S) { await Swal.fire('Sheet tidak ada', 'Indeks sheet tidak valid.', 'warning'); return }
  if (!S.preview.length) { await Swal.fire('Belum ada preview', `Klik "Cek Data (Sheet Ini)" pada ${S.name} dulu.`, 'info'); return }

  const picked = S.preview.filter(p => p._selected)
  if (!picked.length) { await Swal.fire('Tidak ada yang dipilih', `Pilih (centang) minimal 1 baris di ${S.name}.`, 'info'); return }

  const { isConfirmed } = await Swal.fire({
    icon:'question',
    title:`Konfirmasi Import — ${S.name}`,
    html:`Periode: <b>${byNum[startInfo.month].long} ${startInfo.year}</b><br>Dipilih: <b>${picked.length}</b> baris`,
    showCancelButton:true,
    confirmButtonText:'Mulai Import',
    cancelButtonText:'Batal'
  })
  if (!isConfirmed) return

  S.importing = true
  S.importTotal = picked.length
  S.importDone = 0

  let created=0, skipped=0, failed=0
  const skippedInfo = []
  const errs = []
  const seenBatch = new Set()

  const tanggalDefault = ymdFromMonth(startInfo.year, startInfo.month)
  const tanggalSheet   = S.overrideTanggalUkur || tanggalDefault

  for (const p of picked) {
    try {
      const anak = await resolveAnak({ nik: p.nik, id: p.id })
      if (!anak) { skipped++; skippedInfo.push({ nik:p.nik, reason:'Anak tidak ditemukan di sistem' }); continue }

      const umur = p.umur_bulan
      if (umur != null && umur >= AGE_SKIP_MONTHS) { skipped++; skippedInfo.push({ nik:p.nik, reason:'Umur ≥ batas (60 bln)' }); continue }
      if (p._notHadir) { skipped++; skippedInfo.push({ nik:p.nik, reason:'Tidak hadir (BB/TB kosong)' }); continue }

      const tanggal_ukur = tanggalSheet

      const key = `${p.nik}__${tanggal_ukur}`
      if (seenBatch.has(key)) { skipped++; skippedInfo.push({ nik:p.nik, reason:'Duplikat dalam batch (NIK+Tanggal sama)' }); continue }
      seenBatch.add(key)

      const already = await existsPengukuran(p.nik, tanggal_ukur)
      const posyandu_id = anak?.posyandu_id || anak?.posyandu?.id || p.child.posyandu_id || null
      const cara_ukur = (umur != null && umur < 24) ? 'Terlentang' : 'Berdiri'

      const isVitMonth = (() => { const d = new Date(tanggal_ukur); const m = d.getMonth()+1; return m === 2 || m === 8 })()

      const payload = {
        anak_id: anak.id,
        tanggal_ukur,
        posyandu_id,
        berat: p.bb ?? null,
        tinggi: p.tb ?? null,
        cara_ukur,
        vit_a: isVitMonth ? (p.vit_a || null) : null,
        asi_bulan_0: !!(p.asi?.includes?.(0) || p.asi?.[0]),
        asi_bulan_1: !!(p.asi?.includes?.(1) || p.asi?.[1]),
        asi_bulan_2: !!(p.asi?.includes?.(2) || p.asi?.[2]),
        asi_bulan_3: !!(p.asi?.includes?.(3) || p.asi?.[3]),
        asi_bulan_4: !!(p.asi?.includes?.(4) || p.asi?.[4]),
        asi_bulan_5: !!(p.asi?.includes?.(5) || p.asi?.[5]),
        asi_bulan_6: !!(p.asi?.includes?.(6) || p.asi?.[6]),
        kelas_ibu_balita: !!p.kelas_ibu,
      }

      if (already) { skipped++; skippedInfo.push({ nik:p.nik, reason:`Sudah ada pengukuran pada ${tanggal_ukur}` }); continue }

      await api.post('/anak-pengukuran', payload, { headers:{Accept:'application/json'} })
      created++
    } catch (e) {
      failed++; errs.push({ nik: p.nik || p.nik_display, reason: e?.response?.data?.message || 'Gagal import' })
    } finally {
      S.importDone++
      if (S.importDone % 50 === 0) await new Promise(r => setTimeout(r, 0))
    }
  }

  S.importing = false
  setTimeout(() => { S.importTotal = 0; S.importDone = 0 }, 1000)

  const skippedHtml = skippedInfo.length
    ? `<hr><div class="small"><b>Detail Skipped (${skippedInfo.length}):</b><br>${skippedInfo.slice(0,20).map(s=>`${s.nik}: ${s.reason}`).join('<br>')}${skippedInfo.length>20?'<br>…':''}</div>`
    : ''

  const html = `
    <div class="text-start">
      <div><b>Sheet:</b> ${S.name}</div>
      <div><b>Periode:</b> ${byNum[startInfo.month].long} ${startInfo.year}</div>
      <div><b>Diproses:</b> ${picked.length}</div>
      <hr>
      <div>✅ Created: <b>${created}</b></div>
      <div>❌ Failed: <b>${failed}</b></div>
      <div>⏭️ Skipped: <b>${skipped}</b> <span class="text-muted">(sudah ada / duplikat batch / usia ≥ limit / tidak hadir)</span></div>
      ${errs.length ? `<hr><div class="small"><b>Gagal:</b><br>${errs.slice(0,8).map(e=>`${e.nik}: ${e.reason}`).join('<br>')}${errs.length>8?'<br>…':''}</div>` : ''}
      ${skippedHtml}
    </div>`
  await Swal.fire({ icon: failed ? 'warning' : 'success', title:`Selesai — ${S.name}`, html })
}

</script>

<template>
  <div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <RouterLink
          :to="{ name: 'anak-pengukuran.index' }"
          class="btn btn-md btn-secondary rounded shadow border-0 d-inline-flex align-items-center"
        >
          ← Kembali
        </RouterLink>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <div class="card border-0 rounded shadow">
          <div class="card-body">
            <h5 class="mb-3">Import Pengukuran Anak (ASP25_MASTER.xlsx)</h5>

            <div class="mb-3">
              <label class="form-label fw-bold">File Excel (.xls/.xlsx)</label>
              <input type="file" class="form-control" accept=".xls,.xlsx" @change="handleFiles" />
              <div class="form-text">
                Bulan/Tahun dari <b>Start!E6/H5</b>, data dari <b>P1–P10</b> (header 3 baris, sheet kosong tetap ditampilkan).
              </div>
            </div>

            <div v-if="startInfo.month && startInfo.year" class="alert alert-secondary py-2">
              <b>Periode:</b> {{ startInfo.label }}
            </div>

            <div v-if="parsing" class="alert alert-info">Membaca file…</div>

            <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
              <button class="btn btn-primary" :disabled="parsing || importing || checkingAll || !sheets.length" @click="buildPreviewAll">
                <span v-if="checkingAll" class="spinner-border spinner-border-sm me-2"></span>
                {{ checkingAll ? 'Mengecek semua…' : 'Cek Semua Sheet' }}
              </button>

              <button class="btn btn-success" :disabled="importing || !sheets.some(s => s.preview.length)" @click="doImport">
                <span v-if="importing" class="spinner-border spinner-border-sm me-2"></span>
                {{ importing ? 'Mengimpor…' : 'Mulai Import Pengukuran (Baris Dicentang)' }}
              </button>
            </div>

            <div v-if="checkingAll" class="alert alert-info d-flex align-items-center gap-2">
              <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
              <span>Mengecek semua sheet… ({{ checkProgress.done }} / {{ checkProgress.total }})</span>
            </div>

            <div v-if="checkingAll || importing || checkProgress.done || progress.done" class="my-3">
              <div class="mb-2" v-if="checkingAll || checkProgress.done">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <strong class="text-info">Cek Data</strong>
                  <small>{{ checkProgress.done }} / {{ checkProgress.total }}</small>
                </div>
                <div class="progress" style="height: 18px;">
                  <div
                    class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                    role="progressbar"
                    :style="{ width: ((checkProgress.done / checkProgress.total)*100 || 0) + '%' }"
                    aria-valuemin="0" aria-valuemax="100"
                  ></div>
                </div>
              </div>

              <div v-if="importing || progress.done">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <strong class="text-success">Impor</strong>
                  <small>{{ progress.done }} / {{ progress.total }}</small>
                </div>
                <div class="progress" style="height: 20px;">
                  <div
                    class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                    role="progressbar"
                    :style="{ width: ((progress.done / progress.total)*100 || 0) + '%' }"
                    aria-valuemin="0" aria-valuemax="100"
                  ></div>
                </div>
              </div>
            </div>

            <div class="accordion" id="accSheets" v-if="sheets.length">
              <div class="accordion-item" v-for="(S,si) in sheets" :key="S.name">
                <h2 class="accordion-header" :id="`heading-${S.name}`">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" :data-bs-target="`#collapse-${S.name}`" aria-expanded="false" :aria-controls="`collapse-${S.name}`">
                    <template v-if="S._sheetMissing">
                      {{ S.name }} — <span class="text-danger fw-bold">sheet tidak ditemukan</span>
                    </template>
                    <template v-else-if="S._sheetEmpty">
                      {{ S.name }} — <span class="text-muted">kosong</span>
                    </template>
                    <template v-else>
                      {{ S.name }} — Kandidat: {{ S.rows.length }} | Preview: {{ S.preview.length || 0 }} | Belum terdaftar: {{ S.preview.filter(x=>!x.exists).length || 0 }}
                    </template>
                  </button>
                </h2>
                <div :id="`collapse-${S.name}`" class="accordion-collapse collapse" :aria-labelledby="`heading-${S.name}`" data-bs-parent="#accSheets">
                  <div class="accordion-body">

                    <div v-if="S._sheetMissing" class="alert alert-warning mb-0">
                      Sheet {{ S.name }} tidak ditemukan di file Excel.
                    </div>

                    <div v-else-if="S._sheetEmpty" class="alert alert-secondary mb-0">
                      Sheet {{ S.name }} ada tetapi tidak berisi data yang bermakna.
                    </div>

                    <template v-else>
                      <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                        <button class="btn btn-outline-primary" :disabled="!S.rows.length || S.checking" @click="buildPreviewFor(si)">
                          <span v-if="S.checking" class="spinner-border spinner-border-sm me-2"></span>
                          {{ S.checking ? 'Mengecek…' : 'Cek Data (Sheet Ini)' }}
                        </button>

                        <button class="btn btn-success"
                                :disabled="S.importing || !S.preview.some(p => p._selected)"
                                @click="doImportSheet(si)">
                          <span v-if="S.importing" class="spinner-border spinner-border-sm me-2"></span>
                          {{ S.importing ? 'Mengimpor…' : 'Mulai Import (Sheet Ini)' }}
                        </button>

                        <button class="btn btn-outline-dark" :disabled="!S.preview.length || S.checking" @click="exportMissingToXLSBySheet(si)">
                          Export XLS Anak Belum Ada — {{ S.preview.filter(p => !p.exists).length }}
                        </button>

                        <div class="input-group" style="max-width: 360px;">
                          <span class="input-group-text">Tgl Ukur</span>
                          <input type="date" class="form-control" v-model="S.tempTanggalUkur" />
                          <button class="btn btn-success" type="button" @click="applyTanggalSheet(si)">Apply</button>
                        </div>
                        <small class="text-muted ms-1">
                          Aktif: {{ S.overrideTanggalUkur || ymdFromMonth(startInfo.year, startInfo.month) }}
                        </small>

                        <div class="btn-group ms-1">
                          <button class="btn btn-sm btn-outline-secondary"
                                  :disabled="!S.preview.length"
                                  @click="S.preview.forEach(p => p._selected = true)">
                            Centang semua
                          </button>
                          <button class="btn btn-sm btn-outline-secondary"
                                  :disabled="!S.preview.length"
                                  @click="S.preview.forEach(p => p._selected = false)">
                            Bersihkan
                          </button>
                          <button class="btn btn-sm btn-outline-primary"
                                  :disabled="!S.preview.length"
                                  @click="S.preview.forEach(p => p._selected = !!p._eligible)">
                            Centang yg siap impor
                          </button>
                        </div>
                        <small class="text-muted ms-2" v-if="S.preview.length">
                          Dipilih: {{ S.preview.filter(p=>p._selected).length }} / {{ S.preview.length }}
                        </small>
                      </div>

                      <div v-if="S.checking || S.checkDone" class="mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                          <strong class="text-primary">Cek {{ S.name }}</strong>
                          <small>{{ S.checkDone }} / {{ S.checkTotal }}</small>
                        </div>
                        <div class="progress" style="height: 14px;">
                          <div
                            class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                            role="progressbar"
                            :style="{ width: ((S.checkDone / S.checkTotal)*100 || 0) + '%' }"
                          ></div>
                        </div>
                      </div>

                      <div v-if="S.importing || S.importDone" class="mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                          <strong class="text-success">Impor {{ S.name }}</strong>
                          <small>{{ S.importDone }} / {{ S.importTotal }}</small>
                        </div>
                        <div class="progress" style="height: 16px;">
                          <div
                            class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                            role="progressbar"
                            :style="{ width: ((S.importDone / S.importTotal)*100 || 0) + '%' }"
                          ></div>
                        </div>
                      </div>

                      <div v-if="S.preview.length" class="table-responsive mt-2">
                        <table class="table table-bordered table-striped align-middle">
                          <thead class="bg-dark text-white">
                            <tr>
                              <th style="width:42px;">
                                <input
                                  class="form-check-input"
                                  type="checkbox"
                                  :checked="S.preview.length && S.preview.every(p => p._selected)"
                                  @change="(e) => { const on = e.target.checked; S.preview.forEach(p => p._selected = on); S.selectAll = on; }"
                                  :title="'Centang/Batalkan semua di ' + S.name"
                                />
                              </th>
                              <th>#</th>
                              <th>NIK</th>
                              <th>Nama</th>
                              <th>Tgl Lahir</th>
                              <th>Umur (bln)</th>
                              <th>Desa</th>
                              <th>Posyandu</th>
                              <th>Berat</th>
                              <th>Tinggi/Panjang</th>
                              <th>Cara Ukur</th>
                              <th>Vit. A</th>
                              <th>ASI 0–6</th>
                              <th>Kelas Ibu</th>
                              <th>Status</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(p,i) in S.preview.slice(0,150)" :key="(p.nik || p.nik_display) + '-' + i">
                              <td>
                                <input
                                  class="form-check-input"
                                  type="checkbox"
                                  v-model="p._selected"
                                  :disabled="!p._eligible"
                                  :title="p._eligible ? 'Pilih baris ini untuk diimpor' : 'Baris ini tidak memenuhi syarat impor'"
                                />
                              </td>
                              <td>{{ i+1 }}</td>
                              <td>{{ p.nik_display }}</td>
                              <td>{{ p.nama }}</td>
                              <td>{{ p.tgl_lahir || '-' }}</td>
                              <td>{{ p.umur_bulan ?? '-' }}</td>
                              <td>{{ p.desa || '-' }}</td>
                              <td>{{ p.posy || '-' }}</td>
                              <td>{{ p.bb ?? '-' }} kg</td>
                              <td>{{ p.tb ?? '-' }} cm</td>
                              <td>{{ p.cara_ukur }}</td>
                              <td>{{ p.vit_a_display || '-' }}</td>
                              <td>
                                <template v-if="p.asi?.length">
                                  <span v-for="b in p.asi" :key="b" class="badge bg-success me-1 mb-1">Bulan {{ b }}</span>
                                </template>
                                <span v-else class="text-muted">-</span>
                              </td>
                              <td>
                                <span v-if="p.kelas_ibu" class="badge bg-success">Ya</span>
                                <span v-else>-</span>
                              </td>
                              <td>
                                <span
                                  :class="[
                                    'badge',
                                    (p._ageOver)              ? 'bg-dark text-white fw-bold' :
                                    (p._notHadir)             ? 'bg-warning text-dark'       :
                                    (p.status.startsWith('Perhatian')) ? 'bg-warning text-dark' :
                                    p.status.startsWith('Siap') ? 'bg-success' :
                                    p.status.startsWith('Skip') ? 'bg-secondary' :
                                    'bg-danger'
                                  ]"
                                >
                                  {{ p.status }}
                                  <template v-if="p.warns?.length">
                                    <br><small class="text-muted">{{ p.warns.join(' · ') }}</small>
                                  </template>
                                </span>
                              </td>
                            </tr>
                            <tr v-if="S.preview.length > 150">
                              <td colspan="15" class="text-center text-muted">…menampilkan 150 baris pertama.</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </template>

                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-muted">Belum ada sheet P1–P10 yang terbaca.</div>

            <div class="small text-muted mt-3">
              Catatan: NIK kurang dari 10 digit/kosong ditampilkan sebagai <code>-/(BELUM ADA)</code>. Baris tanpa isi bermakna (angka 0, tanda '-', placeholder) tidak akan dihitung kandidat.
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</template>
