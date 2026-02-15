<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../api'

const router = useRouter()
const route = useRoute()
const idParam = route.params.id

// STATE
const anak = ref(null)
const loadingFetch = ref(false)
const loading = ref(false)
const generalError = ref('')
const errors = ref({})

// form fields mirror backend
const nik = ref('')
const anak_ke = ref('')
const tgl_lahir = ref('')
const jenis_kelamin = ref('')
const nomor_KK = ref('')
const nama_anak = ref('')
const usia_hamil = ref('')
const berat_lahir = ref('')
const panjang_lahir = ref('')
const lingkar_kepala_lahir = ref('')
const kia = ref(false)
const kia_bayi_kecil = ref(false)
const imd = ref(false)
const nama_ortu = ref('')
const nik_ortu = ref('')
const hp_ortu = ref('')
const posyandu_id = ref('')
const rt = ref('')
const rw = ref('')

// posyandu list
const posyandus = ref([])
const loadingPosyandu = ref(false)

const posyanduLabel = (p) => {
  const desa = (p?.desa ?? '').toUpperCase()
  const nm = p?.nama ?? ''
  if (desa && nm) return `Desa ${desa}-Posy. ${nm}`
  if (desa) return `Desa ${desa}`
  if (nm) return `Posy. ${nm}`
  return `ID: ${p?.id ?? '-'}`
}

const fetchPosyandu = async () => {
  loadingPosyandu.value = true
  try {
    const res = await api.get('/posyandu', { headers: { Accept: 'application/json' } })
    const data = res.data
    posyandus.value = Array.isArray(data)
      ? data
      : Array.isArray(data?.data) ? data.data
      : Array.isArray(data?.data?.data) ? data.data.data
      : []
  } catch {
    posyandus.value = []
  } finally {
    loadingPosyandu.value = false
  }
}

const pick = (resData) => {
  if (resData?.data && typeof resData.data === 'object') return resData.data
  if (resData && typeof resData === 'object') return resData
  return null
}

const loadAnak = async () => {
  loadingFetch.value = true
  generalError.value = ''
  errors.value = {}
  try {
    const res = await api.get(`/anak/${idParam}`, { headers: { Accept: 'application/json' } })
    const a = pick(res.data)
    anak.value = a

    // prefill
    nik.value = a?.nik ?? ''
    anak_ke.value = a?.anak_ke ?? ''
    tgl_lahir.value = a?.tgl_lahir ?? ''
    jenis_kelamin.value = a?.jenis_kelamin ?? ''
    nomor_KK.value = a?.nomor_KK ?? ''
    nama_anak.value = a?.nama_anak ?? ''
    usia_hamil.value = a?.usia_hamil ?? ''
    berat_lahir.value = a?.berat_lahir ?? ''
    panjang_lahir.value = a?.panjang_lahir ?? ''
    lingkar_kepala_lahir.value = a?.lingkar_kepala_lahir ?? ''
    kia.value = !!a?.kia
    kia_bayi_kecil.value = !!a?.kia_bayi_kecil
    imd.value = !!a?.imd
    nama_ortu.value = a?.nama_ortu ?? ''
    nik_ortu.value = a?.nik_ortu ?? ''
    hp_ortu.value = a?.hp_ortu ?? ''
    posyandu_id.value = a?.posyandu_id ?? ''
    rt.value = a?.rt ?? ''
    rw.value = a?.rw ?? ''
  } catch (err) {
    const data = err?.response?.data
    generalError.value = data?.message ?? 'Gagal memuat data.'
  } finally {
    loadingFetch.value = false
  }
}

onMounted(async () => {
  await fetchPosyandu()
  await loadAnak()
})


const updateAnak = async () => {
  errors.value = {}
  if (!tgl_lahir.value) errors.value.tgl_lahir = ['Tanggal lahir wajib diisi.']
  if (!(jenis_kelamin.value === 'L' || jenis_kelamin.value === 'P')) {
    errors.value.jenis_kelamin = ['Jenis kelamin wajib dipilih.']
  }
  if (!nama_anak.value.trim()) errors.value.nama_anak = ['Nama anak wajib diisi.']
  if (Object.keys(errors.value).length) return

  loading.value = true
  generalError.value = ''
  try {
    const payload = {
      nik: nik.value,
      anak_ke: anak_ke.value || null,
      tgl_lahir: tgl_lahir.value,
      jenis_kelamin: jenis_kelamin.value,
      nomor_KK: nomor_KK.value,
      nama_anak: nama_anak.value.trim(),
      usia_hamil: usia_hamil.value || null,
      berat_lahir: berat_lahir.value || null,
      panjang_lahir: panjang_lahir.value || null,
      lingkar_kepala_lahir: lingkar_kepala_lahir.value || null,
      kia: !!kia.value,
      kia_bayi_kecil: !!kia_bayi_kecil.value,
      imd: !!imd.value,
      nama_ortu: nama_ortu.value || null,
      nik_ortu: nik_ortu.value || null,
      hp_ortu: hp_ortu.value || null,
      posyandu_id: posyandu_id.value || null,
      rt: rt.value || null,
      rw: rw.value || null,
    }

    await api.put(`/anak/${idParam}`, payload, { headers: { Accept: 'application/json' } })
    router.push({ name: 'anak.index' })
  } catch (err) {
    const data = err?.response?.data
    errors.value = data?.errors ?? data ?? {}
    generalError.value = data?.message ?? 'Gagal menyimpan perubahan.'
    console.log('Update failed:', data)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <RouterLink
          :to="{ name: 'anak.index' }"
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
            <h5 class="mb-3">Edit Anak (NIK: {{ nik }})</h5>

            <div v-if="loadingFetch" class="alert alert-info">Memuat data…</div>
            <div v-else>
              <form @submit.prevent="updateAnak">
                <div v-if="generalError" class="alert alert-danger">{{ generalError }}</div>

                <div class="row g-3">
                  <div class="col-md-3">
                    <label class="form-label fw-bold">NIK</label>
                    <input
                      type="text"
                      class="form-control"
                      v-model="nik"
                      maxlength="16"
                    />
                    <div v-if="errors?.nik?.length" class="alert alert-danger mt-2">{{ errors.nik[0] }}</div>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-bold">Anak Ke</label>
                    <input type="number" min="1" class="form-control" v-model="anak_ke" />
                    <div v-if="errors?.anak_ke?.length" class="alert alert-danger mt-2">{{ errors.anak_ke[0] }}</div>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-bold">Tgl Lahir</label>
                    <input type="date" class="form-control" v-model="tgl_lahir" />
                    <div v-if="errors?.tgl_lahir?.length" class="alert alert-danger mt-2">{{ errors.tgl_lahir[0] }}</div>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-bold">Jenis Kelamin</label>
                    <select class="form-select" v-model="jenis_kelamin">
                      <option value="">-- Pilih --</option>
                      <option value="L">Laki-Laki</option>
                      <option value="P">Perempuan</option>
                    </select>
                    <div v-if="errors?.jenis_kelamin?.length" class="alert alert-danger mt-2">{{ errors.jenis_kelamin[0] }}</div>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-bold">Nama Anak</label>
                    <input class="form-control" v-model="nama_anak" />
                    <div v-if="errors?.nama_anak?.length" class="alert alert-danger mt-2">{{ errors.nama_anak[0] }}</div>
                  </div>
                </div>

                <div class="row g-3 mt-1">
                  <div class="col-md-4">
                    <label class="form-label fw-bold">Nomor KK</label>
                    <input class="form-control" v-model="nomor_KK" name="nomor_KK" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-bold">Usia Hamil (minggu)</label>
                    <input class="form-control" v-model="usia_hamil" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-bold">Berat Lahir (gram)</label>
                    <input class="form-control" v-model="berat_lahir" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-bold">Panjang Lahir (cm)</label>
                    <input class="form-control" v-model="panjang_lahir" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-bold">Lingkar Kepala Lahir (cm)</label>
                    <input class="form-control" v-model="lingkar_kepala_lahir" />
                  </div>

                  <div class="col-md-4 d-flex align-items-end flex-wrap gap-3">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="kia" v-model="kia" />
                      <label class="form-check-label" for="kia">KIA</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="kia_bayi_kecil" v-model="kia_bayi_kecil" />
                      <label class="form-check-label" for="kia_bayi_kecil">KIA Bayi Kecil</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="imd" v-model="imd" />
                      <label class="form-check-label" for="imd">IMD</label>
                    </div>
                  </div>
                </div>

                <div class="row g-3 mt-1">
                  <div class="col-md-4">
                    <label class="form-label fw-bold">Nama Ortu</label>
                    <input class="form-control" v-model="nama_ortu" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-bold">NIK Ortu</label>
                    <input class="form-control" v-model="nik_ortu" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-bold">HP Ortu</label>
                    <input class="form-control" v-model="hp_ortu" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-bold">Posyandu (opsional)</label>
                    <select class="form-select" v-model="posyandu_id">
                      <option value="">-- Pilih Posyandu --</option>
                      <option v-for="p in posyandus" :key="p.id" :value="p.id">
                        {{ posyanduLabel(p) }}
                      </option>
                    </select>
                    <div class="form-text" v-if="loadingPosyandu">Memuat daftar posyandu…</div>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-bold">RT</label>
                    <input class="form-control" v-model="rt" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-bold">RW</label>
                    <input class="form-control" v-model="rw" />
                  </div>
                </div>

                <div class="mt-4">
                  <button class="btn btn-primary" :disabled="loading">
                    {{ loading ? 'Saving…' : 'Save Changes' }}
                  </button>
                </div>
              </form>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style media="screen">
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

input[type=number] {
  -moz-appearance: textfield;
}
</style>
