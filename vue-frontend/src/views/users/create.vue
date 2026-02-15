<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'

const router = useRouter()

// state
const username = ref('')
const password = ref('')
const passwordConfirm = ref('')

const showPassword = ref(false)
const showPasswordConfirm = ref(false)

const errors = ref({})
const generalError = ref('')
const loading = ref(false)

// validasi ringan di frontend (opsional tapi membantu UX)
const canSubmit = computed(() =>
  !loading &&
  username.value?.trim().length > 0 &&
  password.value.length >= 6 &&
  password.value === passwordConfirm.value
)

// submit
const storeUser = async () => {
  loading.value = true
  generalError.value = ''
  errors.value = {}

  try {
    const formData = new FormData()
    formData.append('username', username.value)
    formData.append('password', password.value)
    formData.append('password_confirmation', passwordConfirm.value) // penting utk rule confirmed

    await api.post('/users', formData, {
      headers: { Accept: 'application/json' },
    })

    router.push({ path: '/users' })
  } catch (err) {
    const data = err?.response?.data
    // Validator::errors() dikirim sebagai object field -> [msg]
    errors.value = data?.errors ?? data ?? {}
    generalError.value = data?.message ?? 'Gagal menyimpan data.'
    console.log('Save failed:', data)
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
            <!-- PENTING: panggil storeUser, bukan submit -->
            <form @submit.prevent="storeUser">
              <div v-if="generalError" class="alert alert-danger">{{ generalError }}</div>

              <div class="mb-3">
                <label class="form-label fw-bold">Username</label>
                <input class="form-control" v-model="username" placeholder="Username" />
                <div v-if="errors?.username?.length" class="alert alert-danger mt-2">
                  {{ errors.username[0] }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-bold">Password</label>
                <div class="input-group">
                  <input
                    :type="showPassword ? 'text' : 'password'"
                    class="form-control"
                    v-model="password"
                    placeholder="Password (min. 6)"
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

              <div class="mb-3">
                <label class="form-label fw-bold">Konfirmasi Password</label>
                <div class="input-group">
                  <input
                    :type="showPasswordConfirm ? 'text' : 'password'"
                    class="form-control"
                    v-model="passwordConfirm"
                    placeholder="Ulangi password"
                    autocomplete="new-password"
                  />
                  <span class="input-group-text" style="cursor:pointer" @click="showPasswordConfirm = !showPasswordConfirm">
                    <font-awesome-icon :icon="showPasswordConfirm ? 'fa-eye-slash' : 'fa-eye'" />
                  </span>
                </div>
                <div v-if="errors?.password_confirmation?.length" class="alert alert-danger mt-2">
                  {{ errors.password_confirmation[0] }}
                </div>
                <div v-else-if="password && passwordConfirm && password !== passwordConfirm" class="text-danger mt-2">
                  Password dan konfirmasi tidak sama.
                </div>
              </div>

              <button class="btn btn-primary" :disabled="loading">
                {{ loading ? 'Saving…' : 'Save' }}
              </button>
              <small class="text-muted ms-2" v-if="!loading">
                Min. 6 karakter & harus sama dengan konfirmasi.
              </small>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
