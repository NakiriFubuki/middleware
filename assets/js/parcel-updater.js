/**
 * Parcel Status Updater
 */
const ParcelStatus = {
    init() {
        const form = document.getElementById('statusUpdateForm');
        if (!form) return;

        this.form = form;
        this.parcelId = form.dataset.parcelId;
        this.statusSelect = document.getElementById('parcelStatus');
        this.photoSection = document.getElementById('photoUploadSection');

        this.statusSelect?.addEventListener('change', () => {
            if (this.statusSelect.value === 'delivered' && !this.hasDeliveryPhoto()) {
                Toast.warning('Please upload a delivery photo first.');
                this.statusSelect.value = this.statusSelect.dataset.lastValue || 'out_for_delivery';
                return;
            }

            if (this.statusSelect.value === 'delivered' || this.statusSelect.value === 'out_for_delivery') {
                this.photoSection?.classList.remove('hidden');
            }

            this.statusSelect.dataset.lastValue = this.statusSelect.value;
        });

        if (this.statusSelect) {
            this.statusSelect.dataset.lastValue = this.statusSelect.value;
        }

        this.updateDeliveredOption();
        form.addEventListener('submit', (e) => this.handleSubmit(e));
    },

    hasDeliveryPhoto() {
        if (this.form?.dataset.hasPhoto === '1') {
            return true;
        }
        return !!document.querySelector('#photoGallery .photo-item');
    },

    setHasDeliveryPhoto(hasPhoto) {
        if (this.form) {
            this.form.dataset.hasPhoto = hasPhoto ? '1' : '0';
        }
        this.updateDeliveredOption();
        document.getElementById('photoRequiredNote')?.classList.toggle('hidden', hasPhoto);
    },

    updateDeliveredOption() {
        const deliveredOpt = this.statusSelect?.querySelector('option[value="delivered"]');
        if (!deliveredOpt || this.statusSelect.value === 'delivered') {
            return;
        }
        deliveredOpt.disabled = !this.hasDeliveryPhoto();
    },

    async handleSubmit(e) {
        e.preventDefault();

        const status = this.statusSelect.value;
        const remarks = document.getElementById('statusRemarks').value;

        if (status === 'delivered' && !this.hasDeliveryPhoto()) {
            Toast.warning('Please upload a delivery photo before marking as delivered.');
            this.photoSection?.classList.remove('hidden');
            return;
        }

        try {
            const res = await Ajax.post(Ajax.getBaseUrl() + '/api/update_status.php', {
                parcel_id: parseInt(this.parcelId, 10),
                status,
                remarks
            });

            if (res.success) {
                Toast.success(res.message);

                const badge = document.getElementById('currentStatus');
                if (badge) {
                    const labels = {
                        pending: 'Pending',
                        out_for_delivery: 'Out For Delivery',
                        delivered: 'Delivered',
                        failed: 'Failed Delivery'
                    };
                    badge.textContent = labels[status] || status;
                    badge.className = 'badge badge-' + getStatusClass(status);
                }

                if (res.requires_photo || status === 'out_for_delivery') {
                    this.photoSection?.classList.remove('hidden');
                }

                if (this.statusSelect) {
                    this.statusSelect.dataset.lastValue = status;
                }

                appendTimeline(status, remarks);
            } else {
                Toast.error(res.message);
                if (res.requires_photo) {
                    this.photoSection?.classList.remove('hidden');
                }
            }
        } catch (err) {
            Toast.error('Failed to update status.');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => ParcelStatus.init());

function getStatusClass(status) {
    const map = {
        pending: 'warning',
        out_for_delivery: 'info',
        delivered: 'success',
        failed: 'danger'
    };
    return map[status] || 'secondary';
}

function appendTimeline(status, remarks) {
    const timeline = document.getElementById('statusTimeline');
    if (!timeline) return;

    const labels = {
        pending: 'Pending',
        out_for_delivery: 'Out For Delivery',
        delivered: 'Delivered',
        failed: 'Failed Delivery'
    };

    const item = document.createElement('div');
    item.className = 'timeline-item';
    item.innerHTML = `
        <div class="timeline-marker"></div>
        <div class="timeline-content">
            <span class="badge badge-${getStatusClass(status)}">${labels[status]}</span>
            <p>${remarks || ''}</p>
            <small>Just now</small>
        </div>
    `;
    timeline.appendChild(item);
}
