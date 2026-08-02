import { ref, onBeforeUnmount, type Ref } from 'vue'
import {
  FaceLandmarker,
  FilesetResolver,
  type FaceLandmarkerResult,
} from '@mediapipe/tasks-vision'

export type LivenessChallenge = 'blink' | 'turn-left' | 'turn-right'

const CHALLENGES: LivenessChallenge[] = ['blink', 'turn-left', 'turn-right']

const CHALLENGE_LABEL: Record<LivenessChallenge, string> = {
  blink: 'Kedipkan mata 2x',
  'turn-left': 'Tolehkan kepala ke kiri',
  'turn-right': 'Tolehkan kepala ke kanan',
}

// Tuning liveness detection.
const BLINK_TARGET = 2
const BLINK_CLOSE_THRESHOLD = 0.5
const BLINK_OPEN_THRESHOLD = 0.25
const TURN_THRESHOLD = 0.15

// Index landmark dari 468-point face mesh MediaPipe.
const LM_NOSE_TIP = 1
const LM_LEFT_FACE_EDGE = 234
const LM_RIGHT_FACE_EDGE = 454

// Landmarker dimuat sekali dan dipakai ulang lintas sesi (biar gak reload model tiap buka kamera).
let landmarkerPromise: Promise<FaceLandmarker> | null = null

function loadLandmarker() {
  if (!landmarkerPromise) {
    landmarkerPromise = FilesetResolver.forVisionTasks(
      'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@latest/wasm',
    ).then((vision) =>
      FaceLandmarker.createFromOptions(vision, {
        baseOptions: {
          modelAssetPath:
            'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
          delegate: 'GPU',
        },
        outputFaceBlendshapes: true,
        runningMode: 'VIDEO',
        numFaces: 1,
      }),
    )
  }
  return landmarkerPromise
}

export function useFaceLiveness(videoRef: Ref<HTMLVideoElement | null | undefined>) {
  const challenge = ref<LivenessChallenge>('blink')
  const challengeLabel = ref(CHALLENGE_LABEL.blink)
  const isPassed = ref(false)
  const isLoading = ref(false)
  const loadError = ref('')

  let landmarker: FaceLandmarker | null = null
  let rafId = 0
  let blinkCount = 0
  let eyeWasClosed = false

  function pickChallenge() {
    challenge.value = CHALLENGES[Math.floor(Math.random() * CHALLENGES.length)]
    challengeLabel.value = CHALLENGE_LABEL[challenge.value]
    blinkCount = 0
    eyeWasClosed = false
    isPassed.value = false
  }

  function evaluateBlink(result: FaceLandmarkerResult) {
    const categories = result.faceBlendshapes?.[0]?.categories ?? []
    const left = categories.find((c) => c.categoryName === 'eyeBlinkLeft')?.score ?? 0
    const right = categories.find((c) => c.categoryName === 'eyeBlinkRight')?.score ?? 0
    const avg = (left + right) / 2

    if (avg > BLINK_CLOSE_THRESHOLD) {
      eyeWasClosed = true
      return
    }
    if (avg < BLINK_OPEN_THRESHOLD && eyeWasClosed) {
      eyeWasClosed = false
      blinkCount += 1
      if (blinkCount >= BLINK_TARGET) {
        isPassed.value = true
      }
    }
  }

  function evaluateTurn(result: FaceLandmarkerResult) {
    const landmarks = result.faceLandmarks?.[0]
    if (!landmarks) return

    const nose = landmarks[LM_NOSE_TIP]
    const leftEdge = landmarks[LM_LEFT_FACE_EDGE]
    const rightEdge = landmarks[LM_RIGHT_FACE_EDGE]
    const faceWidth = rightEdge.x - leftEdge.x
    if (faceWidth <= 0) return

    // Offset posisi hidung dari titik tengah wajah, dinormalisasi lebar wajah.
    // Catatan: landmark dihitung dari frame kamera asli (bukan versi -scale-x-100
    // yang ditampilkan di UI), jadi offset positif = wajah noleh ke kanan pengguna.
    const center = (leftEdge.x + rightEdge.x) / 2
    const offset = (nose.x - center) / faceWidth

    if (challenge.value === 'turn-left' && offset > TURN_THRESHOLD) {
      isPassed.value = true
    } else if (challenge.value === 'turn-right' && offset < -TURN_THRESHOLD) {
      isPassed.value = true
    }
  }

  function loop() {
    const video = videoRef.value
    if (!video || !landmarker || isPassed.value) return

    if (video.readyState >= 2) {
      const result = landmarker.detectForVideo(video, performance.now())
      if (challenge.value === 'blink') {
        evaluateBlink(result)
      } else {
        evaluateTurn(result)
      }
    }

    rafId = requestAnimationFrame(loop)
  }

  async function start() {
    pickChallenge()
    isLoading.value = true
    loadError.value = ''
    try {
      landmarker = await loadLandmarker()
      isLoading.value = false
      rafId = requestAnimationFrame(loop)
    } catch {
      isLoading.value = false
      loadError.value = 'Gagal memuat model liveness detection.'
    }
  }

  function stop() {
    if (rafId) cancelAnimationFrame(rafId)
    rafId = 0
  }

  onBeforeUnmount(stop)

  return {
    challengeLabel,
    isPassed,
    isLoading,
    loadError,
    start,
    stop,
  }
}