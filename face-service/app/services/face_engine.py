import base64
import io
from typing import List, Optional

import numpy as np
from PIL import Image
from deepface import DeepFace

from app.config import settings

_DEFAULT_THRESHOLDS = {
    "cosine": 0.68,
    "euclidean": 4.15,
    "euclidean_l2": 1.13,
}


def _decode_image(image_base64: str) -> np.ndarray:
    try:
        image_bytes = base64.b64decode(image_base64)
    except Exception as exc:
        raise ValueError("image_base64 tidak valid (gagal di-decode).") from exc

    try:
        image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
    except Exception as exc:
        raise ValueError("Data gambar tidak bisa dibaca.") from exc

    return np.array(image)


def encode_face(image_base64: str) -> dict:
    image = _decode_image(image_base64)

    try:
        representations = DeepFace.represent(
            img_path=image,
            model_name=settings.model_name,
            detector_backend=settings.detector_backend,
            enforce_detection=True,
        )
    except ValueError as exc:
        raise ValueError(f"Tidak ada wajah terdeteksi pada gambar: {exc}") from exc

    if not representations:
        raise ValueError("Tidak ada wajah terdeteksi pada gambar.")

    face = representations[0]

    return {
        "embedding": face["embedding"],
        "model": settings.model_name,
        "facial_area": face.get("facial_area"),
    }


def check_liveness(image_base64: str) -> dict:
    image = _decode_image(image_base64)

    try:
        faces = DeepFace.extract_faces(
            img_path=image,
            detector_backend=settings.detector_backend,
            enforce_detection=True,
            anti_spoofing=True,
        )
    except ValueError as exc:
        raise ValueError(f"Tidak ada wajah terdeteksi pada gambar: {exc}") from exc

    if not faces:
        raise ValueError("Tidak ada wajah terdeteksi pada gambar.")

    face = faces[0]

    return {
        "is_live": bool(face.get("is_real", False)),
        "confidence": float(face.get("antispoof_score", 0.0)),
    }


def _distance(a: List[float], b: List[float]) -> float:
    vec_a = np.array(a, dtype=float)
    vec_b = np.array(b, dtype=float)

    if settings.distance_metric == "cosine":
        return float(1 - np.dot(vec_a, vec_b) / (np.linalg.norm(vec_a) * np.linalg.norm(vec_b)))

    if settings.distance_metric == "euclidean_l2":
        vec_a = vec_a / np.linalg.norm(vec_a)
        vec_b = vec_b / np.linalg.norm(vec_b)
        return float(np.linalg.norm(vec_a - vec_b))

    return float(np.linalg.norm(vec_a - vec_b))


def _threshold() -> float:
    if settings.match_threshold is not None:
        return settings.match_threshold
    return _DEFAULT_THRESHOLDS.get(settings.distance_metric, 0.68)


def recognize_face(image_base64: str, candidates: List[dict]) -> dict:
    if not candidates:
        raise ValueError("Daftar candidate embedding kosong.")

    probe_embedding = encode_face(image_base64)["embedding"]
    threshold = _threshold()

    best_employee_id: Optional[int] = None
    best_distance = float("inf")

    for candidate in candidates:
        distance = _distance(probe_embedding, candidate["embedding"])
        if distance < best_distance:
            best_distance = distance
            best_employee_id = candidate["employee_id"]

    is_match = best_distance <= threshold

    return {
        "employee_id": best_employee_id if is_match else None,
        "distance": best_distance,
        "threshold": threshold,
        "is_match": is_match,
    }