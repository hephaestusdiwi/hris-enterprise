from pydantic_settings import BaseSettings
from typing import Optional


class Settings(BaseSettings):
    api_key: str = "secret"
    model_name: str = "ArcFace"
    detector_backend: str = "retinaface"
    distance_metric: str = "cosine"
    match_threshold: Optional[float] = None

    class Config:
        env_prefix = "FACE_"


settings = Settings()