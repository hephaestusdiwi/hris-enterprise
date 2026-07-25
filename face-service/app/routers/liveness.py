from fastapi import APIRouter, Depends, HTTPException

from app.schemas import LivenessRequest, LivenessResponse
from app.security import verify_api_key
from app.services import face_engine

router = APIRouter()


@router.post("/liveness", response_model=LivenessResponse, dependencies=[Depends(verify_api_key)])
def liveness(payload: LivenessRequest):
    try:
        return face_engine.check_liveness(payload.image_base64)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc