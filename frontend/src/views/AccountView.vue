<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { useBabyStore } from '@/stores/baby'

const router = useRouter()
const auth = useAuthStore()
const babyStore = useBabyStore()

function firstErrorMessage(error, fallback) {
  const errors = error.response?.data?.errors
  if (errors) {
    const first = Object.values(errors)[0]
    if (first?.[0]) return first[0]
  }
  return error.response?.data?.message || fallback
}

// ---- Profile ----
const name = ref(auth.user?.name || '')
const email = ref(auth.user?.email || '')
const profileSaving = ref(false)
const profileMessage = ref(null)

async function saveProfile() {
  profileSaving.value = true
  profileMessage.value = null
  try {
    await auth.updateProfile({ name: name.value, email: email.value })
    profileMessage.value = { type: 'success', text: 'Profile updated ✓' }
  } catch (error) {
    profileMessage.value = { type: 'error', text: firstErrorMessage(error, 'Could not update profile.') }
  } finally {
    profileSaving.value = false
  }
}

// ---- Password ----
const currentPassword = ref('')
const newPassword = ref('')
const newPasswordConfirm = ref('')
const passwordSaving = ref(false)
const passwordMessage = ref(null)

async function savePassword() {
  passwordSaving.value = true
  passwordMessage.value = null
  try {
    await auth.updatePassword({
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: newPasswordConfirm.value,
    })
    currentPassword.value = ''
    newPassword.value = ''
    newPasswordConfirm.value = ''
    passwordMessage.value = { type: 'success', text: 'Password changed ✓' }
  } catch (error) {
    passwordMessage.value = { type: 'error', text: firstErrorMessage(error, 'Could not change password.') }
  } finally {
    passwordSaving.value = false
  }
}

// ---- Baby details ----
const babyName = ref('')
const babyBirthDate = ref('')
const babySex = ref('unspecified')
const babySaving = ref(false)
const babyMessage = ref(null)

onMounted(async () => {
  await babyStore.load()
  if (babyStore.currentBaby) {
    babyName.value = babyStore.currentBaby.name
    babyBirthDate.value = babyStore.currentBaby.birth_date
    babySex.value = babyStore.currentBaby.sex || 'unspecified'
  }
})

async function saveBaby() {
  babySaving.value = true
  babyMessage.value = null
  try {
    await babyStore.updateBaby({ name: babyName.value, birth_date: babyBirthDate.value, sex: babySex.value })
    babyMessage.value = { type: 'success', text: 'Baby details updated ✓' }
  } catch (error) {
    babyMessage.value = { type: 'error', text: firstErrorMessage(error, 'Could not update baby details.') }
  } finally {
    babySaving.value = false
  }
}

// ---- Logout ----
const confirmingLogout = ref(false)

async function doLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="screen">
    <div class="top-bar">
      <h1>Account &amp; Baby</h1>
    </div>

    <div class="settings-section-title">Your profile</div>
    <div class="card">
      <div class="field">
        <label>Name</label>
        <input v-model="name" type="text" />
      </div>
      <div class="field">
        <label>Email</label>
        <input v-model="email" type="email" />
      </div>
      <p v-if="profileMessage" class="form-message" :class="profileMessage.type">{{ profileMessage.text }}</p>
      <button class="btn btn-primary btn-block" :disabled="profileSaving" @click="saveProfile">
        {{ profileSaving ? 'Saving…' : 'Save profile' }}
      </button>
    </div>

    <div class="settings-section-title">Change password</div>
    <div class="card">
      <div class="field">
        <label>Current password</label>
        <input v-model="currentPassword" type="password" autocomplete="current-password" />
      </div>
      <div class="field">
        <label>New password</label>
        <input v-model="newPassword" type="password" autocomplete="new-password" />
      </div>
      <div class="field">
        <label>Confirm new password</label>
        <input v-model="newPasswordConfirm" type="password" autocomplete="new-password" />
      </div>
      <p v-if="passwordMessage" class="form-message" :class="passwordMessage.type">{{ passwordMessage.text }}</p>
      <button class="btn btn-primary btn-block" :disabled="passwordSaving" @click="savePassword">
        {{ passwordSaving ? 'Saving…' : 'Change password' }}
      </button>
    </div>

    <div class="settings-section-title">Baby details</div>
    <div class="card">
      <div class="field">
        <label>Name</label>
        <input v-model="babyName" type="text" />
      </div>
      <div class="field">
        <label>Date of birth</label>
        <input v-model="babyBirthDate" type="date" />
      </div>
      <div class="field">
        <label>Sex</label>
        <div class="segmented">
          <button type="button" :class="{ active: babySex === 'female' }" @click="babySex = 'female'">Girl</button>
          <button type="button" :class="{ active: babySex === 'male' }" @click="babySex = 'male'">Boy</button>
          <button type="button" :class="{ active: babySex === 'unspecified' }" @click="babySex = 'unspecified'">—</button>
        </div>
      </div>
      <p v-if="babyMessage" class="form-message" :class="babyMessage.type">{{ babyMessage.text }}</p>
      <button class="btn btn-primary btn-block" :disabled="babySaving" @click="saveBaby">
        {{ babySaving ? 'Saving…' : 'Save baby details' }}
      </button>
    </div>

    <div class="settings-section-title">Session</div>
    <div class="card">
      <button type="button" class="btn btn-danger btn-block" @click="confirmingLogout = true">Log out</button>
    </div>

    <ConfirmDialog
      v-if="confirmingLogout"
      title="Log out?"
      message="You'll need to sign in again to keep logging entries."
      confirm-label="Log out"
      icon="👋"
      @confirm="doLogout"
      @cancel="confirmingLogout = false"
    />
  </div>
</template>
