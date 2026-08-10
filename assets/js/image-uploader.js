/**
 * Image Uploader with camera support and compression
 */
const ImageUploader = {
    stream: null,
    parcelId: null,

    init() {
        const form = document.getElementById('statusUpdateForm');
        if (!form) return;

        this.parcelId = form.dataset.parcelId;

        document.getElementById('startCameraBtn')?.addEventListener('click', () => this.startCamera());
        document.getElementById('captureBtn')?.addEventListener('click', () => this.capturePhoto());
        document.getElementById('uploadPhotoBtn')?.addEventListener('click', () => this.uploadPhoto());
        document.getElementById('fileInput')?.addEventListener('change', (e) => this.handleFileSelect(e));
    },

    async startCamera() {
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false
            });

            const video = document.getElementById('cameraPreview');
            video.srcObject = this.stream;
            video.classList.remove('hidden');
            document.getElementById('captureBtn')?.classList.remove('hidden');
            document.getElementById('startCameraBtn')?.classList.add('hidden');
        } catch (e) {
            Toast.error('Camera access denied or unavailable.');
        }
    },

    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        document.getElementById('cameraPreview')?.classList.add('hidden');
    },

    capturePhoto() {
        const video = document.getElementById('cameraPreview');
        const canvas = document.getElementById('photoCanvas');
        const preview = document.getElementById('photoPreview');

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        this.compressCanvas(canvas, 0.7, (dataUrl) => {
            preview.src = dataUrl;
            preview.classList.remove('hidden');
            canvas.dataset.imageData = dataUrl;
            document.getElementById('uploadPhotoBtn')?.classList.remove('hidden');
        });

        this.stopCamera();
    },

    handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            Toast.error('Invalid file type.');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            Toast.error('File too large (max 5MB).');
            return;
        }

        const reader = new FileReader();
        reader.onload = (ev) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const maxDim = 1200;
                let w = img.width, h = img.height;

                if (w > maxDim || h > maxDim) {
                    if (w > h) { h = (h / w) * maxDim; w = maxDim; }
                    else { w = (w / h) * maxDim; h = maxDim; }
                }

                canvas.width = w;
                canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);

                this.compressCanvas(canvas, 0.8, (dataUrl) => {
                    const preview = document.getElementById('photoPreview');
                    preview.src = dataUrl;
                    preview.classList.remove('hidden');
                    document.getElementById('photoCanvas').dataset.imageData = dataUrl;
                    document.getElementById('uploadPhotoBtn')?.classList.remove('hidden');
                });
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    },

    compressCanvas(canvas, quality, callback) {
        callback(canvas.toDataURL('image/jpeg', quality));
    },

    async uploadPhoto() {
        const canvas = document.getElementById('photoCanvas');
        const imageData = canvas.dataset.imageData;

        if (!imageData) {
            Toast.warning('No photo to upload.');
            return;
        }

        const formData = new FormData();
        formData.append('parcel_id', this.parcelId);
        formData.append('image_data', imageData);

        try {
            const res = await Ajax.post(Ajax.getBaseUrl() + '/api/upload_photo.php', formData);
            if (res.success) {
                Toast.success(res.message);
                document.getElementById('uploadPhotoBtn')?.classList.add('hidden');
                document.getElementById('photoPreview')?.classList.add('hidden');
                document.getElementById('photoCanvas').dataset.imageData = '';

                const gallery = document.getElementById('photoGallery');
                const noMsg = document.getElementById('noPhotosMsg');
                if (noMsg) noMsg.remove();

                if (gallery && res.file_path) {
                    const item = document.createElement('div');
                    item.className = 'photo-item';
                    item.innerHTML = `<img src="${Ajax.getBaseUrl()}/${res.file_path}" alt="Proof"><small>Just now</small>`;
                    gallery.appendChild(item);
                }

                if (typeof ParcelStatus !== 'undefined') {
                    ParcelStatus.setHasDeliveryPhoto(true);
                }
            } else {
                Toast.error(res.message);
            }
        } catch (e) {
            Toast.error('Upload failed.');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => ImageUploader.init());
