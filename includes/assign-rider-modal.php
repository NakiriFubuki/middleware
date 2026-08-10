<?php
$assignRiders = $assignRiders ?? getActiveRiders();
?>
<div class="modal" id="assignModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Assign Rider</h3>
            <button class="modal-close" data-close-modal type="button">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="assignParcelId" value="">
            <div class="form-group">
                <label for="assignRiderId">Select Rider</label>
                <select id="assignRiderId" class="form-control">
                    <option value="">Choose rider...</option>
                    <?php foreach ($assignRiders as $r): ?>
                        <option value="<?= (int) $r['id'] ?>">
                            <?= sanitize($r['full_name']) ?> (<?= sanitize($r['rider_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close-modal type="button">Cancel</button>
            <button class="btn btn-primary" id="confirmAssignBtn" type="button">Assign</button>
        </div>
    </div>
</div>
