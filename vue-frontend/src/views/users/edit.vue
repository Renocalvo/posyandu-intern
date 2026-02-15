<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../api'

const router = useRouter()
const route = useRoute()
const id = route.params.id

// state
const username = ref('')
const password = ref('')
const passwordConfirm = ref('')

const showPassword = ref(false)
const showPasswordConfirm = ref(false)

const errors = ref({})
const generalError = ref('')
const loading = ref(false)
const loadingFetch = ref(false)

// helper: ambil field dari respons yang bervariasi
const pickUserFromResponse = (resData) => {
  // coba: { data: {...} } -> {...}
  if (resData?.data && typeof resData.data === 'object' && !Array.isArray(resData.data)) {
    return resData.data
  }
  // fallback: langsung objek
  if (resData && typeof resData === 'object') return resData
  return {}
}

// LOAD: prefill username dari server
const loadUser = async () => {
  loadingFetch.value = true
  generalError.value = ''
  errors.value = {}
  try {
    const res = await api.get(`/users/${id}`, { headers: { Accept: 'application/json' } })
    const user = pickUserFromResponse(res.data)
    username.value = user?.username ?? ''
  } catch (err) {
    const data = err?.response?.data
    generalError.value = data?.message ?? 'Gagal memuat data pengguna.'
    console.log('Fetch user failed:', data)
  } finally {
    loadingFetch.value = false
  }
}

onMounted(loadUser)

// validasi ringan (opsional)
const passwordProvided = computed(() => password.value.length > 0 || passwordConfirm.value.length > 0)
const passwordMatch = computed(() => !passwordProvided.value || password.value === passwordConfirm.value)
const passwordLenOK = computed(() => !passwordProvided.value || password.value.length >= 6)

const canSubmit = computed(() =>
  !loading &&
  username.value.trim().length > 0 &&
  passwordMatch.value &&
  passwordLenOK.value
)

// SUBMIT: update user
const updateUser = async () => {
  loading.value = true
  generalError.value = ''
  errors.value = {}

  try {
    // Kirim JSON; password hanya dikirim jika diisi (biar tidak wajib ganti)
    const payload = { username: username.value }
    if (password.value) {
      payload.password = password.value
      payload.password_confirmation = passwordConfirm.value
    }

    // PUT atau PATCH, sesuaikan dengan route di Laravel
    await api.put(`/users/${id}`, payload, { headers: { Accept: 'application/json' } })
    // alternatif:
    // await api.patch(`/users/${id}`, payload, { headers: { Accept: 'application/json' } })

    router.push({ path: '/users' })
  } catch (err) {
    const data = err?.response?.data
    // Untuk Validator::errors() bisa langsung object { field: [msg] }
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
          :to="{ name: 'users.index' }"
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
            <h5 class="mb-3">Edit User</h5>

            <div v-if="loadingFetch" class="alert alert-info">Memuat data…</div>
            <div v-else>
              <form @submit.prevent="updateUser">
                <div v-if="generalError" class="alert alert-danger">{{ generalError }}</div>

                <div class="mb-3">
                  <label class="form-label fw-bold">Username</label>
                  <input class="form-control" v-model="username" placeholder="Username" />
                  <div v-if="errors?.username?.length" class="alert alert-danger mt-2">
                    {{ errors.username[0] }}
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-bold">Password (opsional)</label>
                  <div class="input-group">
                    <input
                      :type="showPassword ? 'text' : 'password'"
                      class="form-control"
                      v-model="password"
                      placeholder="Isi bila ingin mengganti (min. 6)"
                      autocomplete="new-password"
                    />
                    <span class="input-group-text" style="cursor:pointer" @click="showPassword = !showPassword">
                      <font-awesome-icon :icon="showPassword ? 'fa-eye-slash' : 'fa-eye'" />
                    </span>
                  </div>
                  <div v-if="errors?.password?.length" class="alert alert-danger mt-2">
                    {{ errors.password[0] }}
                  </div>
                </div>

                <div class="mb-3" v-if="password || passwordConfirm">
                  <label class="form-label fw-bold">Konfirmasi Password</label>
                  <div class="input-group">
                    <input
                      :type="showPasswordConfirm ? 'text' : 'password'"
                      class="form-control"
                      v-model="passwordConfirm"
                      placeholder="Ulangi password baru"
                      autocomplete="new-password"
                    />
                    <span class="input-group-text" style="cursor:pointer" @click="showPasswordConfirm = !showPasswordConfirm">
                      <font-awesome-icon :icon="showPasswordConfirm ? 'fa-eye-slash' : 'fa-eye'" />
                    </span>
                  </div>
                  <div v-if="errors?.password_confirmation?.length" class="alert alert-danger mt-2">
                    {{ errors.password_confirmation[0] }}
                  </div>
                  <div v-else-if="!passwordMatch" class="text-danger mt-2">
                    Password dan konfirmasi tidak sama.
                  </div>
                  <div v-else-if="!passwordLenOK" class="text-danger mt-2">
                    Password minimal 6 karakter.
                  </div>
                </div>

                <button class="btn btn-primary" :disabled="loading">
                  {{ loading ? 'Saving…' : 'Save' }}
                </button>
                <small class="text-muted ms-2" v-if="!loading">
                  * Kosongkan password bila tidak ingin mengubah
                </small>
              </form>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</template>
