import api from '../api'

export async function logout() {
  try {
    await api.post('/logout')
  } catch (error) {
    console.error('Logout failed:', error)
  } finally {
    localStorage.removeItem('access_token')
    localStorage.removeItem('user')
    delete api.defaults.headers.common['Authorization']
  }
}
