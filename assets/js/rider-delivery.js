/**
 * Rider delivery actions - Start delivery
 */
const RiderDelivery = {
    async startDelivery(parcelId, btn) {
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Starting...';
        }

        try {
            const res = await Ajax.post(Ajax.getBaseUrl() + '/api/start_delivery.php', { parcel_id: parcelId });

            if (res.success) {
                Toast.success(res.message);

                if (typeof GpsTracker !== 'undefined' && !GpsTracker.isTracking && !GpsTracker.permissionDenied) {
                    GpsTracker.startTracking();
                }

                setTimeout(() => {
                    if (btn && btn.dataset.redirect) {
                        window.location.href = btn.dataset.redirect;
                    } else {
                        window.location.reload();
                    }
                }, 600);
            } else {
                Toast.error(res.message);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = btn.dataset.label || 'Start Delivery';
                }
            }
        } catch (e) {
            Toast.error('Failed to start delivery.');
            if (btn) {
                btn.disabled = false;
                btn.textContent = btn.dataset.label || 'Start Delivery';
            }
        }
    },

    bindButtons() {
        document.querySelectorAll('.start-delivery-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.startDelivery(parseInt(btn.dataset.parcelId), btn);
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', () => RiderDelivery.bindButtons());
