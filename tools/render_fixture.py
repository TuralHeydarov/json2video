"""Offline MP4/resize smoke test: no production DB, queues or remote assets."""
import json
from pathlib import Path
import sys
import tempfile
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'renderer'))
from PIL import Image
from app.worker import RenderEngine  # includes production Pillow compatibility
from app.config import Config
from moviepy.editor import VideoFileClip


class FixtureCursor:
    def execute(self, *args):
        pass

    def close(self):
        pass


class FixtureDatabase:
    def cursor(self):
        return FixtureCursor()


with tempfile.TemporaryDirectory() as folder:
    asset = Path(folder) / 'red.png'
    Image.new('RGB', (64, 64), (255, 0, 0)).save(asset)
    Config.TEMP_DIR = str(Path(folder) / 'temp')
    Config.STORAGE_PATH = str(Path(folder) / 'renders')
    Config.STORAGE_URL = 'https://invalid.test/fixture'
    payload = {'width': 320, 'height': 240, 'fps': 10, 'scenes': [{
        'duration': 1, 'background-color': '#101010',
        'elements': [{'type': 'image', 'src': 'https://invalid.test/fixture.png',
                      'width': 320, 'height': 240, 'duration': 1}],
    }]}
    with patch('app.elements.image.download_asset', return_value=str(asset)):
        result = RenderEngine('offline-fixture', payload, 'custom', 'low', FixtureDatabase()).render()
    with VideoFileClip(result['output_path']) as video:
        assert video.size == [320, 240]
        assert abs(video.duration - 1) < 0.11
        pixel = video.get_frame(0.5)[120, 160]
        assert pixel[0] > 200 and pixel[1] < 30 and pixel[2] < 30, pixel
    assert result['file_size_bytes'] > 0
    print('OFFLINE_RENDER_FIXTURE', json.dumps({
        'duration_seconds': result['duration_seconds'],
        'file_size_bytes': result['file_size_bytes'],
        'resize_and_decoded_pixels_verified': True,
    }))
