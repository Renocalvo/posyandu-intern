<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../api'
import Swal from 'sweetalert2'

const posyandu = ref([])
const loading = ref(false)
const errorMsg = ref('')

const rows = computed(() => {
  const u = posyandu.value
  if (Array.isArray(u)) return u
  if (Array.isArray(u?.data)) return u.data
  if (Array.isArray(u?.data?.data)) return u.data.data
  return []
})

const fetchDataUsers = async () => {
  loading.value = true
  errorMsg.value = ''
  await api.get('/posyandu')
    .then((response) => {
      posyandu.value = response.data ?? []
      // DEBUG opsional saat develop:
      // console.log('raw:', response.data)
      // console.log('rows:', Array.isArray(rows.value) ? rows.value.length : 'not array')
    })
    .catch(() => {
      posyandu.value = []
      errorMsg.value = 'Gagal memuat data.'
    })
    .finally(() => {
      loading.value = false
    })
}

onMounted(fetchDataUsers)

const deleteUser = async (id) => {
  const { isConfirmed } = await Swal.fire({
    title: 'Hapus posyandu ini?',
    text: 'Tindakan ini tidak bisa dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    reverseButtons: false,
    customClass: {
      confirmButton: 'btn btn-danger',
      cancelButton: 'btn btn-secondary',
      actions: 'swal2-actions-gap'
    },
    buttonsStyling: false,
  })

  if (!isConfirmed) return

  try {
    await api.delete(`/posyandu/${id}`)
    await Swal.fire({
      title: 'Terhapus',
      text: 'Posyandu berhasil dihapus.',
      icon: 'success',
      timer: 1400,
      showConfirmButton: false,
    })
    fetchDataUsers()
  } catch (e) {
    await Swal.fire({
      title: 'Gagal',
      text: e?.response?.data?.message ?? 'Gagal menghapus data.',
      icon: 'error',
    })
  }
}

</script>

<template>
  <div class="container mt-5 mb-5">
    <div class="row">
      <div class="col-md-12">
        <router-link
          :to="{ name: 'posyandu.create' }"
          class="btn btn-md btn-success rounded shadow border-0 mb-3"
        >
          Tambah Posyandu
        </router-link>

        <div class="card border-0 rounded shadow">
          <div class="card-body">
            <div v-if="loading" class="alert alert-info">Memuat…</div>
            <div v-else-if="errorMsg" class="alert alert-danger">{{ errorMsg }}</div>

            <table v-else class="table table-bordered">
              <thead class="bg-dark text-white">
                <tr>
                  <th scope="col">No</th>
                  <th scope="col">Desa</th>
                  <th scope="col">Nama Posyandu</th>
                  <th scope="col" style="width:15%">Actions</th>
                </tr>
              </thead>

              <tbody>
                <tr v-if="rows.length === 0">
                  <td colspan="4" class="text-center">
                    <div class="alert alert-warning mb-0">Data Belum Tersedia!</div>
                  </td>
                </tr>

                <tr v-for="(posyandu, index) in rows" :key="posyandu?.id ?? index">
                  <td>{{ index+1 }}</td>
                  <td>{{ posyandu?.desa }}</td>
                  <td>{{ posyandu?.nama }}</td>
                  <td class="text-center">
                    <router-link
                      :to="{ name: 'posyandu.edit', params:{ id: posyandu?.id } }"
                      class="btn btn-sm btn-primary rounded-sm shadow border-0 me-2"
                    >
                      EDIT
                    </router-link>
                    <button
                      @click.prevent="deleteUser(posyandu?.id)"
                      class="btn btn-sm btn-danger rounded-sm shadow border-0"
                    >
                      DELETE
                    </button>
                  </td>
                </tr>
              </tbody>

            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style>
.swal2-actions-gap {
  gap: 0.5rem;
}
</style>
