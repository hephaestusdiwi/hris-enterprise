from pydantic import BaseModel
from typing import List, Optional


class EncodeRequest(BaseModel):
    image_base64: str


class EncodeResponse(BaseModel):
    embedding: List[float]
    model: str
    facial_area: Optional[dict] = None


class LivenessRequest(BaseModel):
    image_base64: str


class LivenessResponse(BaseModel):
    is_live: bool
    confidence: float


class CandidateEmbedding(BaseModel):
    employee_id: int
    embedding: List[float]


class RecognizeRequest(BaseModel):
    image_base64: str
    candidates: List[CandidateEmbedding]


class RecognizeResponse(BaseModel):
    employee_id: Optional[int]
    distance: float
    threshold: float
    is_match: bool