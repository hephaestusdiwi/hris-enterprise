from fastapi import FastAPI

from app.routers import encode, health, liveness, recognize

app = FastAPI(title="HRIS Face Recognition Service")

app.include_router(health.router)
app.include_router(encode.router)
app.include_router(liveness.router)
app.include_router(recognize.router)