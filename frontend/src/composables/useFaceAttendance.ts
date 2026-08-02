import { ref, computed, watch, type Ref } from 'vue'
import apiClient from '@/lib/axios'
import { useFaceCapture } from '@/composables/useFaceCapture'
import { useFaceLiveness } from '@/composables/useFaceLiveness'

export type AttendanceType = 'clock-in' | 'clock-out'
export type FaceAttendanceStage = 'camera' | 'submitting' | 'success' | 'error'

const statusLabels: Record<string, string> = {
  present: 'Present',
  late: 'Late',
  absent: 'Absent',
  half_day: 'Half Day',
  leave: 'Leave',
  sick: 'Sick',
  alpha: 'Alpha',
}

export function useFaceAttendance(
  type: Ref<AttendanceType>,
  requiresFaceVerification: Ref<boolean>,
  requiresLocation: Ref<boolean>,
) {
  const stage = ref<FaceAttendanceStage>('camera')
  const submitError = ref('')
  const resultData = ref<any>(null)

  const {
    capturedImage,
    base64Only,
    videoRef,
    canvasRef,
    startCamera: startCameraStream,
    stopCamera: stopCameraStream,
    capturePhoto,
  } = useFaceCapture()

  const {
    challengeLabel: livenessLabel,
    isPassed: livenessPassed,
    isLoading: livenessLoading,
    loadError: livenessError,
    start: startLivenessCheck,
    stop: stopLivenessCheck,
  } = useFaceLiveness(videoRef)

  const endpoint = computed(() =>
    type.value === 'clock-in' ? '/api/attendance/clock-in' : '/api/attendance/clock-out',
  )

  const verificationLabel = computed(() =>
    requiresFaceVerification.value ? 'Face Recognition' : 'Photo Verification',
  )

  const resultTime = computed(() =>
    type.value === 'clock-in' ? resultData.value?.clock_in : resultData.value?.clock_out,
  )

  const resultDistance = computed(() =>
    type.value === 'clock-in'
      ? resultData.value?.clock_in_distance_meters
      : resultData.value?.clock_out_distance_meters,
  )

  // Liveness cuma jadi gerbang lokal di browser (boleh capture atau belum).
  // Matching wajah aslinya tetap diproses backend seperti semula, gak disentuh sama sekali.
  const canCapture = computed(() => {
    if (!requiresFaceVerification.value) return true
    if (livenessError.value) return true // fail-open: gagal load model tidak memblokir absen
    return livenessPassed.value
  })

  function getCurrentPosition(): Promise<{ latitude: number; longitude: number }> {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('Perangkat/browser ini tidak mendukung GPS.'))
        return
      }

      navigator.geolocation.getCurrentPosition(
        (position) => {
          resolve({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
          })
        },
        (error) => {
          let message = 'Gagal mendapatkan lokasi GPS.'
          if (error.code === error.PERMISSION_DENIED) {
            message = 'Izin lokasi ditolak. Aktifkan izin lokasi di browser untuk melanjutkan absen.'
          } else if (error.code === error.POSITION_UNAVAILABLE) {
            message = 'Lokasi GPS tidak tersedia saat ini. Pastikan GPS perangkat aktif.'
          } else if (error.code === error.TIMEOUT) {
            message = 'Waktu permintaan lokasi habis, coba lagi.'
          }
          reject(new Error(message))
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
      )
    })
  }

  function formatTime(value: string | null | undefined): string {
    if (!value) return '-'
    const iso = value.includes('T') ? value : value.replace(' ', 'T')
    return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  }

  function statusLabel(status: string | null | undefined): string {
    if (!status) return '-'
    return statusLabels[status] ?? status
  }

  async function startCamera() {
    await startCameraStream()
    if (requiresFaceVerification.value) {
      startLivenessCheck()
    }
  }

  function stopCamera() {
    stopLivenessCheck()
    stopCameraStream()
  }

  async function handleStart() {
    if (!canCapture.value) return
    stopLivenessCheck()
    capturePhoto()
    await submit()
  }

  async function submit() {
    stage.value = 'submitting'
    submitError.value = ''

    let coords: { latitude: number; longitude: number } | null = null

    if (requiresLocation.value) {
      try {
        coords = await getCurrentPosition()
      } catch (err: any) {
        submitError.value = err.message
        stage.value = 'error'
        return
      }
    }
    try {
      const { data } = await apiClient.post(endpoint.value, {
        photo_base64: base64Only.value,
        latitude: coords?.latitude ?? null,
        longitude: coords?.longitude ?? null,
      })
      resultData.value = data.data
      stage.value = 'success'
    } catch (err: any) {
      submitError.value = err.response?.data?.message || 'Gagal melakukan absen, silakan coba lagi.'
      stage.value = 'error'
    }
  }

  function retry() {
    submitError.value = ''
    stage.value = 'camera'
    startCamera()
  }

  function reset() {
    resultData.value = null
    submitError.value = ''
    stage.value = 'camera'
  }

  return {
    stage,
    submitError,
    resultData,
    capturedImage,
    videoRef,
    canvasRef,
    startCamera,
    stopCamera,
    verificationLabel,
    resultTime,
    resultDistance,
    formatTime,
    statusLabel,
    handleStart,
    retry,
    reset,
    // liveness detection (gerbang lokal sebelum capture, bukan pengganti face matching backend)
    livenessLabel,
    livenessPassed,
    livenessLoading,
    livenessError,
    canCapture,
  }
}