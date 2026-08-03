ALTER TABLE carriers ADD COLUMN IF NOT EXISTS address text NOT NULL DEFAULT '';
ALTER TABLE carriers ADD COLUMN IF NOT EXISTS preferred_lanes text NOT NULL DEFAULT '';
ALTER TABLE carriers ADD COLUMN IF NOT EXISTS minimum_rate numeric(8,2) NOT NULL DEFAULT 0;
ALTER TABLE carriers ADD COLUMN IF NOT EXISTS default_fee_percent numeric(5,2) NOT NULL DEFAULT 0.1;
ALTER TABLE carriers ADD COLUMN IF NOT EXISTS first_load_free boolean NOT NULL DEFAULT true;

CREATE TABLE IF NOT EXISTS drivers (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  carrier_id uuid REFERENCES carriers(id) ON DELETE CASCADE,
  name text NOT NULL,
  phone text NOT NULL DEFAULT '',
  email text NOT NULL DEFAULT '',
  equipment_type text NOT NULL DEFAULT 'Dry Van',
  equipment_number text NOT NULL DEFAULT '',
  current_location text NOT NULL DEFAULT '',
  available_at timestamptz,
  status text NOT NULL DEFAULT 'available',
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS invoices (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  carrier_id uuid REFERENCES carriers(id) ON DELETE SET NULL,
  load_id uuid REFERENCES loads(id) ON DELETE SET NULL,
  invoice_number text NOT NULL UNIQUE,
  amount numeric(12,2) NOT NULL DEFAULT 0,
  status text NOT NULL DEFAULT 'draft',
  due_at date,
  created_at timestamptz NOT NULL DEFAULT now()
);
