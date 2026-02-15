export const toYesNo = (v) =>
  v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true' ? 'Ya' : 'Tidak'

export const genderLabel = (jk) =>
  !jk ? '-' : (String(jk).toUpperCase() === 'P' ? 'Perempuan'
       : (String(jk).toUpperCase() === 'L' ? 'Laki-Laki' : jk))

export const fmtDateYMD = (v) => {
  if (!v) return '-'
  if (/^\d{4}-\d{2}-\d{2}$/.test(v)) { const [y,m,d]=v.split('-'); return `${d}/${m}/${y}` }
  const dt = new Date(v)
  return Number.isNaN(dt.getTime()) ? String(v) : dt.toLocaleDateString('id-ID')
}

export const posyanduLabelFromRow = (row) => {
  const p = row?.posyandu || row?.anak?.posyandu || null
  const desa = (p?.desa ?? row?.desa_posyandu ?? row?.anak?.desa_posyandu ?? row?.anak?.desa ?? '').toUpperCase()
  const nama = (p?.nama ?? row?.nama_posyandu ?? row?.anak?.nama_posyandu ?? row?.posyandu_nama ?? '').toUpperCase()
  if (desa && nama) return `Desa ${desa}-Posy. ${nama}`
  if (desa) return `Desa ${desa}`
  if (nama) return `Posy. ${nama}`
  const id = p?.id ?? row?.posyandu_id ?? row?.anak?.posyandu_id
  return id ? `ID: ${id}` : '-'
}
