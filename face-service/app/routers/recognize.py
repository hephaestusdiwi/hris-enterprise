from fastapi import APIRouter, Depends, HTTPException

from app.schemas import RecognizeRequest, RecognizeResponse
from app.security import verify_api_key
from app.services import face_engine

router = APIRouter()


@router.post("/recognize", response_model=RecognizeResponse, dependencies=[Depends(verify_api_key)])
def recognize(payload: RecognizeRequest):
    candidates = [c.model_dump() for c in payload.candidates]

    try:
        return face_engine.recognize_face(payload.image_base64, candidates)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc