from pathlib import Path
import importlib.util

from fastapi.testclient import TestClient


def load_app():
    main_path = Path(__file__).resolve().parents[1] / "main.py"

    spec = importlib.util.spec_from_file_location("boardy_main", main_path)
    module = importlib.util.module_from_spec(spec)

    assert spec is not None
    assert spec.loader is not None

    spec.loader.exec_module(module)

    return module.app


app = load_app()
client = TestClient(app)


def test_health_endpoint_returns_ok():
    response = client.get("/api/health")

    if response.status_code == 404:
        response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {"ok": True}
