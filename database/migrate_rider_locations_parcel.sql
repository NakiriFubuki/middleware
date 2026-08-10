-- Link GPS route points to a specific parcel delivery session.
-- Run once on existing databases: mysql -u root parcel_delivery_db < database/migrate_rider_locations_parcel.sql

USE parcel_delivery_db;

ALTER TABLE rider_locations
    ADD COLUMN parcel_id INT UNSIGNED DEFAULT NULL AFTER rider_id,
    ADD INDEX idx_locations_parcel (parcel_id),
    ADD INDEX idx_locations_rider_parcel (rider_id, parcel_id, created_at);

ALTER TABLE rider_locations
    ADD CONSTRAINT fk_rider_locations_parcel
        FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE SET NULL;
