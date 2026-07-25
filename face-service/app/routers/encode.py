from fastapi import APIRouter, Depends, HTTPException

from app.schemas import EncodeRequest, EncodeResponse
from app.security import verify_api_key
from app.services import face_engine

router = APIRouter()


@router.post("/encode", response_model=EncodeResponse, dependencies=[Depends(verify_api_key)])
def encode(payload: EncodeRequest):
    try:
        return face_engine.encode_face(payload.image_base64)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc