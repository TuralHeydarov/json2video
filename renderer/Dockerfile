FROM python:3.11-slim

# Install FFmpeg and system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    imagemagick \
    libsm6 \
    libxext6 \
    libfontconfig1 \
    fonts-dejavu-core \
    fonts-liberation \
    wget \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Montserrat font
RUN mkdir -p /usr/share/fonts/truetype/montserrat && \
    wget -q -O /usr/share/fonts/truetype/montserrat/Montserrat-Black.ttf \
    "https://github.com/JulietaUla/Montserrat/raw/master/fonts/ttf/Montserrat-Black.ttf" && \
    wget -q -O /usr/share/fonts/truetype/montserrat/Montserrat-Bold.ttf \
    "https://github.com/JulietaUla/Montserrat/raw/master/fonts/ttf/Montserrat-Bold.ttf" && \
    fc-cache -f -v

# Fix ImageMagick security policy (allows MoviePy TextClip to work)
RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
    sed -i 's/<policy domain="path" rights="none" pattern="@\*"/<policy domain="path" rights="read|write" pattern="@*"/' /etc/ImageMagick-6/policy.xml; \
    fi && \
    if [ -f /etc/ImageMagick-7/policy.xml ]; then \
    sed -i 's/<policy domain="path" rights="none" pattern="@\*"/<policy domain="path" rights="read|write" pattern="@*"/' /etc/ImageMagick-7/policy.xml; \
    fi

WORKDIR /app

# Copy requirements first for better caching
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Pre-download Whisper model (baked into image, no download on each start)
ENV WHISPER_MODEL=large-v3-turbo
ENV WHISPER_DEVICE=cpu
RUN python -c "from faster_whisper import WhisperModel; WhisperModel('large-v3-turbo', device='cpu', compute_type='int8')"


# Copy application
COPY . .

# Create temp directory for renders
RUN mkdir -p /tmp/renders /app/storage/renders

CMD ["python", "-m", "app.worker"]
