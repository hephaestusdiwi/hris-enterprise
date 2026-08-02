import { ref, computed, onBeforeUnmount } from 'vue'

export type CaptureStage = 'choose' | 'camera' | 'preview' | 'processing' | 'done'

export function useFaceCapture() {
  const stage = ref<CaptureStage>('choose')
  const errorMessage = ref('')
  const capturedImage = ref('')
  const videoRef = ref<HTMLVideoElement | null>(null)
  const canvasRef = ref<HTMLCanvasElement | null>(null)
  const fileInputRef = ref<HTMLInputElement | null>(null)
  let mediaStream: MediaStream | null = null

  const base64Only = computed(() => capturedImage.value.split(',')[1] ?? '')

  async function startCamera() {
    errorMessage.value = ''
    stage.value = 'camera'

    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
      })
      if (videoRef.value) {
        videoRef.value.srcObject = mediaStream
        await videoRef.value.play()
      }
    } catch {
      errorMessage.value = 'Tidak bisa mengakses kamera. Silakan upload foto sebagai alternatif.'
      stage.value = 'choose'
    }
  }

  function stopCamera() {
    mediaStream?.getTracks().forEach((track) => track.stop())
    mediaStream = null
  }

  function capturePhoto() {
    if (!videoRef.value || !canvasRef.value) return

    const video = videoRef.value
    const canvas = canvasRef.value
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight

    const ctx = canvas.getContext('2d')
    ctx?.drawImage(video, 0, 0, canvas.width, canvas.height)

    capturedImage.value = canvas.toDataURL('image/jpeg', 0.9)
    stopCamera()
    stage.value = 'preview'
  }

  function retakePhoto() {
    capturedImage.value = ''
    startCamera()
  }

  function triggerFileUpload() {
    fileInputRef.value?.click()
  }

  function handleFileChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = () => {
      capturedImage.value = reader.result as string
      stage.value = 'preview'
    }
    reader.readAsDataURL(file)
  }

  function reset() {
    capturedImage.value = ''
    errorMessage.value = ''
    stage.value = 'choose'
  }

  onBeforeUnmount(() => {
    stopCamera()
  })

  return {
    stage,
    errorMessage,
    capturedImage,
    base64Only,
    videoRef,
    canvasRef,
    fileInputRef,
    startCamera,
    stopCamera,
    capturePhoto,
    retakePhoto,
    triggerFileUpload,
    handleFileChange,
    reset,
  }
}