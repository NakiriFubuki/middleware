/**
 * Assign parcel to rider — shared modal handler
 */
const AssignRider = {
    apiUrl: '',

    init() {
        const modal = document.getElementById('assignModal');
        if (!modal) return;

        this.apiUrl = modal.dataset.api || (typeof Ajax !== 'undefined' ? Ajax.getBaseUrl() + '/api/assign_parcel.php' : '');

        document.getElementById('confirmAssignBtn')?.addEventListener('click', () => this.confirm());
        this.bindButtons();
    },

    bindButtons() {
        document.querySelectorAll('.assign-btn').forEach(btn => {
            if (btn.dataset.assignBound === '1') return;
            btn.dataset.assignBound = '1';
            btn.addEventListener('click', () => this.open(btn.dataset.id));
        });
    },

    open(parcelId) {
        const idInput = document.getElementById('assignParcelId');
        const modal = document.getElementById('assignModal');
        if (!idInput || !modal) return;

        idInput.value = parcelId;
        const select = document.getElementById('assignRiderId');
        if (select) select.value = '';
        modal.classList.add('active');
    },

    async confirm() {
        const parcelId = document.getElementById('assignParcelId')?.value;
        const riderId = document.getElementById('assignRiderId')?.value;

        if (!riderId) {
            Toast.warning('Please select a rider.');
            return;
        }

        try {
            const res = await Ajax.post(this.apiUrl, { parcel_id: parcelId, rider_id: riderId });
            if (res.success) {
                Toast.success(res.message);
                setTimeout(() => window.location.reload(), 800);
            } else {
                Toast.error(res.message);
            }
        } catch (e) {
            Toast.error('Failed to assign rider.');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => AssignRider.init());
