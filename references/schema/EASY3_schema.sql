-- EASY3. PostgreSQL 15+. Logical deployment schema.
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE companies (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL,
    currency varchar(3) NOT NULL,
    timezone text NOT NULL,
    status text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL
);

CREATE TABLE accounts (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email text NOT NULL,
    password_hash text NOT NULL,
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    last_login_at timestamptz
);

CREATE TABLE people (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    account_id uuid,
    full_name text NOT NULL,
    job_title text,
    status text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (company_id, id)
);

CREATE TABLE memberships (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    person_id uuid NOT NULL,
    active boolean NOT NULL DEFAULT true,
    joined_at timestamptz NOT NULL,
    disabled_at timestamptz,
    UNIQUE (company_id, id)
);

CREATE TABLE role_bindings (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    membership_id uuid NOT NULL,
    role text NOT NULL,
    scope_kind text NOT NULL,
    scope_id uuid,
    valid_from timestamptz NOT NULL,
    valid_to timestamptz,
    UNIQUE (company_id, id)
);

CREATE TABLE units (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    parent_unit_id uuid,
    kind text NOT NULL,
    name text NOT NULL,
    code text,
    UNIQUE (company_id, id)
);

CREATE TABLE brigades (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    unit_id uuid,
    lead_person_id uuid,
    name text NOT NULL,
    active boolean NOT NULL DEFAULT true,
    UNIQUE (company_id, id)
);

CREATE TABLE brigade_members (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    brigade_id uuid NOT NULL,
    person_id uuid NOT NULL,
    valid_from date NOT NULL,
    valid_to date,
    UNIQUE (company_id, id)
);

CREATE TABLE skills (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    code text NOT NULL,
    name text NOT NULL,
    description text,
    UNIQUE (company_id, id)
);

CREATE TABLE person_skills (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    person_id uuid NOT NULL,
    skill_id uuid NOT NULL,
    level integer,
    verified_at timestamptz,
    valid_until date,
    UNIQUE (company_id, id)
);

CREATE TABLE shift_templates (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    unit_id uuid,
    name text NOT NULL,
    week_days jsonb NOT NULL,
    start_local time NOT NULL,
    end_local time NOT NULL,
    breaks jsonb,
    timezone text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE shift_instances (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    template_id uuid,
    unit_id uuid,
    starts_at timestamptz NOT NULL,
    ends_at timestamptz NOT NULL,
    status text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE calendar_exceptions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    resource_id uuid,
    person_id uuid,
    starts_at timestamptz NOT NULL,
    ends_at timestamptz NOT NULL,
    reason text NOT NULL,
    kind text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE resources (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    unit_id uuid,
    parent_resource_id uuid,
    type text NOT NULL,
    name text NOT NULL,
    quantity numeric(18,4) NOT NULL,
    capacity numeric(18,4),
    unit text,
    status text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE resource_skills (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    resource_id uuid NOT NULL,
    skill_id uuid NOT NULL,
    operation_kind text,
    capacity_ratio numeric(18,4),
    UNIQUE (company_id, id)
);

CREATE TABLE resource_blocks (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    resource_id uuid NOT NULL,
    starts_at timestamptz NOT NULL,
    ends_at timestamptz,
    reason text NOT NULL,
    status text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE materials (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    name text NOT NULL,
    code text,
    unit text NOT NULL,
    owner_kind text NOT NULL,
    attributes jsonb,
    UNIQUE (company_id, id)
);

CREATE TABLE material_balances (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    material_id uuid NOT NULL,
    unit_id uuid,
    available_qty numeric(18,4) NOT NULL,
    reserved_qty numeric(18,4) NOT NULL,
    updated_at timestamptz NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE material_movements (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    material_id uuid NOT NULL,
    order_id uuid,
    batch_id uuid,
    kind text NOT NULL,
    quantity numeric(18,4) NOT NULL,
    unit_cost numeric(18,4),
    occurred_at timestamptz NOT NULL DEFAULT now(),
    source_ref text,
    UNIQUE (company_id, id)
);

CREATE TABLE expense_policies (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    name text NOT NULL,
    cost_kind text NOT NULL,
    allocation_basis text NOT NULL,
    rate numeric(18,4),
    amount numeric(18,4),
    periodicity text,
    effective_from date NOT NULL,
    effective_to date,
    UNIQUE (company_id, id)
);

CREATE TABLE expense_entries (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    policy_id uuid,
    order_id uuid,
    period_from date NOT NULL,
    period_to date NOT NULL,
    amount numeric(18,4) NOT NULL,
    currency varchar(3) NOT NULL,
    source_kind text NOT NULL,
    source_ref text,
    UNIQUE (company_id, id)
);

CREATE TABLE expense_allocations (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    expense_entry_id uuid NOT NULL,
    order_id uuid,
    calculation_id uuid,
    allocated_amount numeric(18,4) NOT NULL,
    basis_quantity numeric(18,4),
    version integer NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE orders (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    code text NOT NULL,
    name text NOT NULL,
    order_kind text NOT NULL,
    status text NOT NULL,
    created_by uuid,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL,
    archived_at timestamptz,
    UNIQUE (company_id, id)
);

CREATE TABLE order_lines (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_id uuid NOT NULL,
    product_name text NOT NULL,
    quantity numeric(18,4) NOT NULL,
    unit text NOT NULL,
    attributes jsonb,
    status text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE order_terms_versions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_id uuid NOT NULL,
    version integer NOT NULL,
    due_at timestamptz,
    budget numeric(18,4),
    sale_price numeric(18,4),
    unit_price numeric(18,4),
    notes text,
    status text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (company_id, id)
);

CREATE TABLE order_requirements (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_line_id uuid NOT NULL,
    material_id uuid,
    quantity numeric(18,4) NOT NULL,
    unit text NOT NULL,
    readiness text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE order_attachments (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_id uuid NOT NULL,
    file_id uuid NOT NULL,
    attachment_kind text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (company_id, id)
);

CREATE TABLE route_versions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_line_id uuid NOT NULL,
    version integer NOT NULL,
    source_kind text NOT NULL,
    state text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    approved_at timestamptz,
    UNIQUE (company_id, id)
);

CREATE TABLE product_parts (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    route_version_id uuid NOT NULL,
    parent_part_id uuid,
    name text NOT NULL,
    quantity numeric(18,4) NOT NULL,
    unit text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE operations (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    route_version_id uuid NOT NULL,
    part_id uuid,
    norm_id uuid,
    title text NOT NULL,
    result_unit text NOT NULL,
    required_skill_id uuid,
    sequence_no integer,
    required_resource_kind text,
    UNIQUE (company_id, id)
);

CREATE TABLE operation_edges (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    route_version_id uuid NOT NULL,
    from_operation_id uuid NOT NULL,
    to_operation_id uuid NOT NULL,
    edge_kind text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE norms (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    name text NOT NULL,
    value numeric(18,4),
    unit text NOT NULL,
    basis text NOT NULL,
    origin text NOT NULL,
    source_ref text,
    valid_from date,
    valid_to date,
    confidence text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE operation_materials (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    operation_id uuid NOT NULL,
    material_id uuid NOT NULL,
    quantity numeric(18,4) NOT NULL,
    unit text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE plan_versions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_id uuid NOT NULL,
    route_version_id uuid,
    version integer NOT NULL,
    state text NOT NULL,
    effective_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    inputs_hash text NOT NULL,
    forecast_at timestamptz,
    UNIQUE (company_id, id)
);

CREATE TABLE plan_allocations (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    plan_version_id uuid NOT NULL,
    operation_id uuid NOT NULL,
    resource_id uuid,
    shift_id uuid,
    starts_at timestamptz,
    ends_at timestamptz,
    quantity numeric(18,4) NOT NULL,
    batch_size numeric(18,4),
    status text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE plan_approvals (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    plan_version_id uuid NOT NULL,
    role text NOT NULL,
    actor_id uuid NOT NULL,
    status text NOT NULL,
    decided_at timestamptz,
    comment text,
    UNIQUE (company_id, id)
);

CREATE TABLE plan_forecasts (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    plan_version_id uuid NOT NULL,
    calculated_at timestamptz NOT NULL DEFAULT now(),
    ready_at timestamptz,
    confidence text NOT NULL,
    bottlenecks jsonb NOT NULL,
    constraints jsonb NOT NULL,
    inputs_hash text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE scenarios (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_id uuid NOT NULL,
    base_plan_version_id uuid NOT NULL,
    name text NOT NULL,
    status text NOT NULL,
    created_by uuid NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (company_id, id)
);

CREATE TABLE scenario_changes (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    scenario_id uuid NOT NULL,
    object_kind text NOT NULL,
    object_id uuid NOT NULL,
    parameter text NOT NULL,
    value_json jsonb NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE scenario_results (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    scenario_id uuid NOT NULL,
    calculated_at timestamptz NOT NULL DEFAULT now(),
    deadline timestamptz,
    load_delta jsonb,
    economy_delta jsonb,
    warnings jsonb,
    UNIQUE (company_id, id)
);

CREATE TABLE batches (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_line_id uuid NOT NULL,
    plan_version_id uuid NOT NULL,
    parent_batch_id uuid,
    quantity numeric(18,4) NOT NULL,
    unit text NOT NULL,
    status text NOT NULL,
    qr_token text,
    UNIQUE (company_id, id)
);

CREATE TABLE assignments (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    plan_allocation_id uuid NOT NULL,
    batch_id uuid NOT NULL,
    worker_id uuid NOT NULL,
    shift_id uuid,
    state text NOT NULL,
    issued_at timestamptz,
    completed_at timestamptz,
    revision integer NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE attendance_events (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    person_id uuid NOT NULL,
    shift_id uuid NOT NULL,
    kind text NOT NULL,
    occurred_at timestamptz NOT NULL DEFAULT now(),
    recorded_by uuid NOT NULL,
    reason text,
    UNIQUE (company_id, id)
);

CREATE TABLE production_events (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    assignment_id uuid NOT NULL,
    batch_id uuid NOT NULL,
    actor_id uuid NOT NULL,
    kind text NOT NULL,
    quantity numeric(18,4) NOT NULL,
    unit text NOT NULL,
    occurred_at timestamptz NOT NULL DEFAULT now(),
    received_at timestamptz NOT NULL DEFAULT now(),
    idempotency_key text NOT NULL,
    correction_of uuid,
    UNIQUE (company_id, id)
);

CREATE TABLE quality_checks (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    batch_id uuid NOT NULL,
    assignment_id uuid,
    inspector_id uuid NOT NULL,
    accepted_qty numeric(18,4) NOT NULL,
    rework_qty numeric(18,4) NOT NULL,
    scrap_qty numeric(18,4) NOT NULL,
    checked_at timestamptz NOT NULL,
    status text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE quality_defects (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    check_id uuid NOT NULL,
    code text NOT NULL,
    quantity numeric(18,4) NOT NULL,
    severity text,
    comment text,
    UNIQUE (company_id, id)
);

CREATE TABLE downtime_events (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    resource_id uuid,
    assignment_id uuid,
    reported_by uuid NOT NULL,
    starts_at timestamptz NOT NULL,
    ends_at timestamptz,
    kind text NOT NULL,
    reason text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE file_objects (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    storage_key text NOT NULL,
    original_name text NOT NULL,
    mime_type text NOT NULL,
    size_bytes bigint NOT NULL,
    sha256 text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (company_id, id)
);

CREATE TABLE event_evidence (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    event_id uuid,
    check_id uuid,
    file_id uuid NOT NULL,
    evidence_kind text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (company_id, id)
);

CREATE TABLE cost_calculations (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_id uuid,
    terms_version_id uuid,
    plan_version_id uuid,
    mode text NOT NULL,
    result_amount numeric(18,4),
    currency varchar(3) NOT NULL,
    completeness text NOT NULL,
    inputs_hash text NOT NULL,
    calculated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (company_id, id)
);

CREATE TABLE cost_lines (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    calculation_id uuid NOT NULL,
    expense_entry_id uuid,
    category text NOT NULL,
    quantity numeric(18,4),
    rate numeric(18,4),
    amount numeric(18,4) NOT NULL,
    origin text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE progress_snapshots (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_id uuid NOT NULL,
    plan_version_id uuid,
    calculated_at timestamptz NOT NULL DEFAULT now(),
    good_qty numeric(18,4) NOT NULL,
    rework_qty numeric(18,4) NOT NULL,
    remaining_qty numeric(18,4) NOT NULL,
    projected_ready_at timestamptz,
    UNIQUE (company_id, id)
);

CREATE TABLE deviations (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    order_id uuid,
    plan_version_id uuid,
    detected_at timestamptz NOT NULL,
    metric text NOT NULL,
    expected_value numeric(18,4),
    observed_value numeric(18,4),
    severity text NOT NULL,
    reason text,
    status text NOT NULL,
    UNIQUE (company_id, id)
);

CREATE TABLE alerts (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    deviation_id uuid,
    recipient_id uuid,
    kind text NOT NULL,
    status text NOT NULL,
    scheduled_at timestamptz,
    delivered_at timestamptz,
    UNIQUE (company_id, id)
);

CREATE TABLE ai_requests (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    actor_id uuid NOT NULL,
    order_id uuid,
    prompt_digest text NOT NULL,
    permitted_scope jsonb NOT NULL,
    status text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (company_id, id)
);

CREATE TABLE ai_suggestions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    request_id uuid NOT NULL,
    scenario_id uuid,
    suggestion_json jsonb NOT NULL,
    safety_status text NOT NULL,
    applied_at timestamptz,
    UNIQUE (company_id, id)
);

CREATE TABLE import_jobs (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    created_by uuid NOT NULL,
    file_id uuid NOT NULL,
    target_kind text NOT NULL,
    mapping jsonb NOT NULL,
    status text NOT NULL,
    started_at timestamptz,
    finished_at timestamptz,
    UNIQUE (company_id, id)
);

CREATE TABLE import_rows (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    import_job_id uuid NOT NULL,
    sheet_name text,
    row_no integer NOT NULL,
    raw_data jsonb NOT NULL,
    normalized_data jsonb,
    error_json jsonb,
    applied_entity_id uuid,
    UNIQUE (company_id, id)
);

CREATE TABLE audit_events (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    actor_id uuid,
    entity_kind text NOT NULL,
    entity_id uuid,
    action text NOT NULL,
    happened_at timestamptz NOT NULL DEFAULT now(),
    old_digest text,
    new_digest text,
    correlation_id uuid,
    UNIQUE (company_id, id)
);

CREATE TABLE outbox_events (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    aggregate_kind text NOT NULL,
    aggregate_id uuid NOT NULL,
    event_kind text NOT NULL,
    payload jsonb NOT NULL,
    occurred_at timestamptz NOT NULL DEFAULT now(),
    published_at timestamptz,
    attempts integer NOT NULL DEFAULT 0,
    UNIQUE (company_id, id)
);

CREATE TABLE idempotency_records (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id uuid NOT NULL,
    actor_id uuid NOT NULL,
    action_kind text NOT NULL,
    request_key text NOT NULL,
    response_json jsonb NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    expires_at timestamptz,
    UNIQUE (company_id, id)
);

ALTER TABLE people ADD CONSTRAINT people_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE people ADD CONSTRAINT people_account_id_fk FOREIGN KEY (account_id) REFERENCES accounts(id);
ALTER TABLE memberships ADD CONSTRAINT memberships_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE memberships ADD CONSTRAINT memberships_person_id_fk FOREIGN KEY (company_id,person_id) REFERENCES people(company_id,id);
ALTER TABLE role_bindings ADD CONSTRAINT role_bindings_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE role_bindings ADD CONSTRAINT role_bindings_membership_id_fk FOREIGN KEY (company_id,membership_id) REFERENCES memberships(company_id,id);
ALTER TABLE units ADD CONSTRAINT units_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE units ADD CONSTRAINT units_parent_unit_id_fk FOREIGN KEY (company_id,parent_unit_id) REFERENCES units(company_id,id);
ALTER TABLE brigades ADD CONSTRAINT brigades_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE brigades ADD CONSTRAINT brigades_unit_id_fk FOREIGN KEY (company_id,unit_id) REFERENCES units(company_id,id);
ALTER TABLE brigades ADD CONSTRAINT brigades_lead_person_id_fk FOREIGN KEY (company_id,lead_person_id) REFERENCES people(company_id,id);
ALTER TABLE brigade_members ADD CONSTRAINT brigade_members_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE brigade_members ADD CONSTRAINT brigade_members_brigade_id_fk FOREIGN KEY (company_id,brigade_id) REFERENCES brigades(company_id,id);
ALTER TABLE brigade_members ADD CONSTRAINT brigade_members_person_id_fk FOREIGN KEY (company_id,person_id) REFERENCES people(company_id,id);
ALTER TABLE skills ADD CONSTRAINT skills_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE person_skills ADD CONSTRAINT person_skills_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE person_skills ADD CONSTRAINT person_skills_person_id_fk FOREIGN KEY (company_id,person_id) REFERENCES people(company_id,id);
ALTER TABLE person_skills ADD CONSTRAINT person_skills_skill_id_fk FOREIGN KEY (company_id,skill_id) REFERENCES skills(company_id,id);
ALTER TABLE shift_templates ADD CONSTRAINT shift_templates_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE shift_templates ADD CONSTRAINT shift_templates_unit_id_fk FOREIGN KEY (company_id,unit_id) REFERENCES units(company_id,id);
ALTER TABLE shift_instances ADD CONSTRAINT shift_instances_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE shift_instances ADD CONSTRAINT shift_instances_template_id_fk FOREIGN KEY (company_id,template_id) REFERENCES shift_templates(company_id,id);
ALTER TABLE shift_instances ADD CONSTRAINT shift_instances_unit_id_fk FOREIGN KEY (company_id,unit_id) REFERENCES units(company_id,id);
ALTER TABLE calendar_exceptions ADD CONSTRAINT calendar_exceptions_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE calendar_exceptions ADD CONSTRAINT calendar_exceptions_resource_id_fk FOREIGN KEY (company_id,resource_id) REFERENCES resources(company_id,id);
ALTER TABLE calendar_exceptions ADD CONSTRAINT calendar_exceptions_person_id_fk FOREIGN KEY (company_id,person_id) REFERENCES people(company_id,id);
ALTER TABLE resources ADD CONSTRAINT resources_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE resources ADD CONSTRAINT resources_unit_id_fk FOREIGN KEY (company_id,unit_id) REFERENCES units(company_id,id);
ALTER TABLE resources ADD CONSTRAINT resources_parent_resource_id_fk FOREIGN KEY (company_id,parent_resource_id) REFERENCES resources(company_id,id);
ALTER TABLE resource_skills ADD CONSTRAINT resource_skills_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE resource_skills ADD CONSTRAINT resource_skills_resource_id_fk FOREIGN KEY (company_id,resource_id) REFERENCES resources(company_id,id);
ALTER TABLE resource_skills ADD CONSTRAINT resource_skills_skill_id_fk FOREIGN KEY (company_id,skill_id) REFERENCES skills(company_id,id);
ALTER TABLE resource_blocks ADD CONSTRAINT resource_blocks_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE resource_blocks ADD CONSTRAINT resource_blocks_resource_id_fk FOREIGN KEY (company_id,resource_id) REFERENCES resources(company_id,id);
ALTER TABLE materials ADD CONSTRAINT materials_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE material_balances ADD CONSTRAINT material_balances_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE material_balances ADD CONSTRAINT material_balances_material_id_fk FOREIGN KEY (company_id,material_id) REFERENCES materials(company_id,id);
ALTER TABLE material_balances ADD CONSTRAINT material_balances_unit_id_fk FOREIGN KEY (company_id,unit_id) REFERENCES units(company_id,id);
ALTER TABLE material_movements ADD CONSTRAINT material_movements_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE material_movements ADD CONSTRAINT material_movements_material_id_fk FOREIGN KEY (company_id,material_id) REFERENCES materials(company_id,id);
ALTER TABLE material_movements ADD CONSTRAINT material_movements_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE material_movements ADD CONSTRAINT material_movements_batch_id_fk FOREIGN KEY (company_id,batch_id) REFERENCES batches(company_id,id);
ALTER TABLE expense_policies ADD CONSTRAINT expense_policies_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE expense_entries ADD CONSTRAINT expense_entries_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE expense_entries ADD CONSTRAINT expense_entries_policy_id_fk FOREIGN KEY (company_id,policy_id) REFERENCES expense_policies(company_id,id);
ALTER TABLE expense_entries ADD CONSTRAINT expense_entries_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE expense_allocations ADD CONSTRAINT expense_allocations_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE expense_allocations ADD CONSTRAINT expense_allocations_expense_entry_id_fk FOREIGN KEY (company_id,expense_entry_id) REFERENCES expense_entries(company_id,id);
ALTER TABLE expense_allocations ADD CONSTRAINT expense_allocations_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE expense_allocations ADD CONSTRAINT expense_allocations_calculation_id_fk FOREIGN KEY (company_id,calculation_id) REFERENCES cost_calculations(company_id,id);
ALTER TABLE orders ADD CONSTRAINT orders_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE orders ADD CONSTRAINT orders_created_by_fk FOREIGN KEY (company_id,created_by) REFERENCES people(company_id,id);
ALTER TABLE order_lines ADD CONSTRAINT order_lines_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE order_lines ADD CONSTRAINT order_lines_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE order_terms_versions ADD CONSTRAINT order_terms_versions_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE order_terms_versions ADD CONSTRAINT order_terms_versions_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE order_requirements ADD CONSTRAINT order_requirements_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE order_requirements ADD CONSTRAINT order_requirements_order_line_id_fk FOREIGN KEY (company_id,order_line_id) REFERENCES order_lines(company_id,id);
ALTER TABLE order_requirements ADD CONSTRAINT order_requirements_material_id_fk FOREIGN KEY (company_id,material_id) REFERENCES materials(company_id,id);
ALTER TABLE order_attachments ADD CONSTRAINT order_attachments_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE order_attachments ADD CONSTRAINT order_attachments_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE order_attachments ADD CONSTRAINT order_attachments_file_id_fk FOREIGN KEY (company_id,file_id) REFERENCES file_objects(company_id,id);
ALTER TABLE route_versions ADD CONSTRAINT route_versions_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE route_versions ADD CONSTRAINT route_versions_order_line_id_fk FOREIGN KEY (company_id,order_line_id) REFERENCES order_lines(company_id,id);
ALTER TABLE product_parts ADD CONSTRAINT product_parts_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE product_parts ADD CONSTRAINT product_parts_route_version_id_fk FOREIGN KEY (company_id,route_version_id) REFERENCES route_versions(company_id,id);
ALTER TABLE product_parts ADD CONSTRAINT product_parts_parent_part_id_fk FOREIGN KEY (company_id,parent_part_id) REFERENCES product_parts(company_id,id);
ALTER TABLE operations ADD CONSTRAINT operations_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE operations ADD CONSTRAINT operations_route_version_id_fk FOREIGN KEY (company_id,route_version_id) REFERENCES route_versions(company_id,id);
ALTER TABLE operations ADD CONSTRAINT operations_part_id_fk FOREIGN KEY (company_id,part_id) REFERENCES product_parts(company_id,id);
ALTER TABLE operations ADD CONSTRAINT operations_norm_id_fk FOREIGN KEY (company_id,norm_id) REFERENCES norms(company_id,id);
ALTER TABLE operations ADD CONSTRAINT operations_required_skill_id_fk FOREIGN KEY (company_id,required_skill_id) REFERENCES skills(company_id,id);
ALTER TABLE operation_edges ADD CONSTRAINT operation_edges_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE operation_edges ADD CONSTRAINT operation_edges_route_version_id_fk FOREIGN KEY (company_id,route_version_id) REFERENCES route_versions(company_id,id);
ALTER TABLE operation_edges ADD CONSTRAINT operation_edges_from_operation_id_fk FOREIGN KEY (company_id,from_operation_id) REFERENCES operations(company_id,id);
ALTER TABLE operation_edges ADD CONSTRAINT operation_edges_to_operation_id_fk FOREIGN KEY (company_id,to_operation_id) REFERENCES operations(company_id,id);
ALTER TABLE norms ADD CONSTRAINT norms_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE operation_materials ADD CONSTRAINT operation_materials_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE operation_materials ADD CONSTRAINT operation_materials_operation_id_fk FOREIGN KEY (company_id,operation_id) REFERENCES operations(company_id,id);
ALTER TABLE operation_materials ADD CONSTRAINT operation_materials_material_id_fk FOREIGN KEY (company_id,material_id) REFERENCES materials(company_id,id);
ALTER TABLE plan_versions ADD CONSTRAINT plan_versions_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE plan_versions ADD CONSTRAINT plan_versions_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE plan_versions ADD CONSTRAINT plan_versions_route_version_id_fk FOREIGN KEY (company_id,route_version_id) REFERENCES route_versions(company_id,id);
ALTER TABLE plan_allocations ADD CONSTRAINT plan_allocations_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE plan_allocations ADD CONSTRAINT plan_allocations_plan_version_id_fk FOREIGN KEY (company_id,plan_version_id) REFERENCES plan_versions(company_id,id);
ALTER TABLE plan_allocations ADD CONSTRAINT plan_allocations_operation_id_fk FOREIGN KEY (company_id,operation_id) REFERENCES operations(company_id,id);
ALTER TABLE plan_allocations ADD CONSTRAINT plan_allocations_resource_id_fk FOREIGN KEY (company_id,resource_id) REFERENCES resources(company_id,id);
ALTER TABLE plan_allocations ADD CONSTRAINT plan_allocations_shift_id_fk FOREIGN KEY (company_id,shift_id) REFERENCES shift_instances(company_id,id);
ALTER TABLE plan_approvals ADD CONSTRAINT plan_approvals_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE plan_approvals ADD CONSTRAINT plan_approvals_plan_version_id_fk FOREIGN KEY (company_id,plan_version_id) REFERENCES plan_versions(company_id,id);
ALTER TABLE plan_approvals ADD CONSTRAINT plan_approvals_actor_id_fk FOREIGN KEY (company_id,actor_id) REFERENCES people(company_id,id);
ALTER TABLE plan_forecasts ADD CONSTRAINT plan_forecasts_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE plan_forecasts ADD CONSTRAINT plan_forecasts_plan_version_id_fk FOREIGN KEY (company_id,plan_version_id) REFERENCES plan_versions(company_id,id);
ALTER TABLE scenarios ADD CONSTRAINT scenarios_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE scenarios ADD CONSTRAINT scenarios_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE scenarios ADD CONSTRAINT scenarios_base_plan_version_id_fk FOREIGN KEY (company_id,base_plan_version_id) REFERENCES plan_versions(company_id,id);
ALTER TABLE scenarios ADD CONSTRAINT scenarios_created_by_fk FOREIGN KEY (company_id,created_by) REFERENCES people(company_id,id);
ALTER TABLE scenario_changes ADD CONSTRAINT scenario_changes_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE scenario_changes ADD CONSTRAINT scenario_changes_scenario_id_fk FOREIGN KEY (company_id,scenario_id) REFERENCES scenarios(company_id,id);
ALTER TABLE scenario_results ADD CONSTRAINT scenario_results_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE scenario_results ADD CONSTRAINT scenario_results_scenario_id_fk FOREIGN KEY (company_id,scenario_id) REFERENCES scenarios(company_id,id);
ALTER TABLE batches ADD CONSTRAINT batches_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE batches ADD CONSTRAINT batches_order_line_id_fk FOREIGN KEY (company_id,order_line_id) REFERENCES order_lines(company_id,id);
ALTER TABLE batches ADD CONSTRAINT batches_plan_version_id_fk FOREIGN KEY (company_id,plan_version_id) REFERENCES plan_versions(company_id,id);
ALTER TABLE batches ADD CONSTRAINT batches_parent_batch_id_fk FOREIGN KEY (company_id,parent_batch_id) REFERENCES batches(company_id,id);
ALTER TABLE assignments ADD CONSTRAINT assignments_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE assignments ADD CONSTRAINT assignments_plan_allocation_id_fk FOREIGN KEY (company_id,plan_allocation_id) REFERENCES plan_allocations(company_id,id);
ALTER TABLE assignments ADD CONSTRAINT assignments_batch_id_fk FOREIGN KEY (company_id,batch_id) REFERENCES batches(company_id,id);
ALTER TABLE assignments ADD CONSTRAINT assignments_worker_id_fk FOREIGN KEY (company_id,worker_id) REFERENCES people(company_id,id);
ALTER TABLE assignments ADD CONSTRAINT assignments_shift_id_fk FOREIGN KEY (company_id,shift_id) REFERENCES shift_instances(company_id,id);
ALTER TABLE attendance_events ADD CONSTRAINT attendance_events_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE attendance_events ADD CONSTRAINT attendance_events_person_id_fk FOREIGN KEY (company_id,person_id) REFERENCES people(company_id,id);
ALTER TABLE attendance_events ADD CONSTRAINT attendance_events_shift_id_fk FOREIGN KEY (company_id,shift_id) REFERENCES shift_instances(company_id,id);
ALTER TABLE attendance_events ADD CONSTRAINT attendance_events_recorded_by_fk FOREIGN KEY (company_id,recorded_by) REFERENCES people(company_id,id);
ALTER TABLE production_events ADD CONSTRAINT production_events_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE production_events ADD CONSTRAINT production_events_assignment_id_fk FOREIGN KEY (company_id,assignment_id) REFERENCES assignments(company_id,id);
ALTER TABLE production_events ADD CONSTRAINT production_events_batch_id_fk FOREIGN KEY (company_id,batch_id) REFERENCES batches(company_id,id);
ALTER TABLE production_events ADD CONSTRAINT production_events_actor_id_fk FOREIGN KEY (company_id,actor_id) REFERENCES people(company_id,id);
ALTER TABLE production_events ADD CONSTRAINT production_events_correction_of_fk FOREIGN KEY (company_id,correction_of) REFERENCES production_events(company_id,id);
ALTER TABLE quality_checks ADD CONSTRAINT quality_checks_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE quality_checks ADD CONSTRAINT quality_checks_batch_id_fk FOREIGN KEY (company_id,batch_id) REFERENCES batches(company_id,id);
ALTER TABLE quality_checks ADD CONSTRAINT quality_checks_assignment_id_fk FOREIGN KEY (company_id,assignment_id) REFERENCES assignments(company_id,id);
ALTER TABLE quality_checks ADD CONSTRAINT quality_checks_inspector_id_fk FOREIGN KEY (company_id,inspector_id) REFERENCES people(company_id,id);
ALTER TABLE quality_defects ADD CONSTRAINT quality_defects_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE quality_defects ADD CONSTRAINT quality_defects_check_id_fk FOREIGN KEY (company_id,check_id) REFERENCES quality_checks(company_id,id);
ALTER TABLE downtime_events ADD CONSTRAINT downtime_events_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE downtime_events ADD CONSTRAINT downtime_events_resource_id_fk FOREIGN KEY (company_id,resource_id) REFERENCES resources(company_id,id);
ALTER TABLE downtime_events ADD CONSTRAINT downtime_events_assignment_id_fk FOREIGN KEY (company_id,assignment_id) REFERENCES assignments(company_id,id);
ALTER TABLE downtime_events ADD CONSTRAINT downtime_events_reported_by_fk FOREIGN KEY (company_id,reported_by) REFERENCES people(company_id,id);
ALTER TABLE file_objects ADD CONSTRAINT file_objects_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE event_evidence ADD CONSTRAINT event_evidence_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE event_evidence ADD CONSTRAINT event_evidence_event_id_fk FOREIGN KEY (company_id,event_id) REFERENCES production_events(company_id,id);
ALTER TABLE event_evidence ADD CONSTRAINT event_evidence_check_id_fk FOREIGN KEY (company_id,check_id) REFERENCES quality_checks(company_id,id);
ALTER TABLE event_evidence ADD CONSTRAINT event_evidence_file_id_fk FOREIGN KEY (company_id,file_id) REFERENCES file_objects(company_id,id);
ALTER TABLE cost_calculations ADD CONSTRAINT cost_calculations_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE cost_calculations ADD CONSTRAINT cost_calculations_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE cost_calculations ADD CONSTRAINT cost_calculations_terms_version_id_fk FOREIGN KEY (company_id,terms_version_id) REFERENCES order_terms_versions(company_id,id);
ALTER TABLE cost_calculations ADD CONSTRAINT cost_calculations_plan_version_id_fk FOREIGN KEY (company_id,plan_version_id) REFERENCES plan_versions(company_id,id);
ALTER TABLE cost_lines ADD CONSTRAINT cost_lines_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE cost_lines ADD CONSTRAINT cost_lines_calculation_id_fk FOREIGN KEY (company_id,calculation_id) REFERENCES cost_calculations(company_id,id);
ALTER TABLE cost_lines ADD CONSTRAINT cost_lines_expense_entry_id_fk FOREIGN KEY (company_id,expense_entry_id) REFERENCES expense_entries(company_id,id);
ALTER TABLE progress_snapshots ADD CONSTRAINT progress_snapshots_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE progress_snapshots ADD CONSTRAINT progress_snapshots_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE progress_snapshots ADD CONSTRAINT progress_snapshots_plan_version_id_fk FOREIGN KEY (company_id,plan_version_id) REFERENCES plan_versions(company_id,id);
ALTER TABLE deviations ADD CONSTRAINT deviations_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE deviations ADD CONSTRAINT deviations_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE deviations ADD CONSTRAINT deviations_plan_version_id_fk FOREIGN KEY (company_id,plan_version_id) REFERENCES plan_versions(company_id,id);
ALTER TABLE alerts ADD CONSTRAINT alerts_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE alerts ADD CONSTRAINT alerts_deviation_id_fk FOREIGN KEY (company_id,deviation_id) REFERENCES deviations(company_id,id);
ALTER TABLE alerts ADD CONSTRAINT alerts_recipient_id_fk FOREIGN KEY (company_id,recipient_id) REFERENCES people(company_id,id);
ALTER TABLE ai_requests ADD CONSTRAINT ai_requests_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE ai_requests ADD CONSTRAINT ai_requests_actor_id_fk FOREIGN KEY (company_id,actor_id) REFERENCES people(company_id,id);
ALTER TABLE ai_requests ADD CONSTRAINT ai_requests_order_id_fk FOREIGN KEY (company_id,order_id) REFERENCES orders(company_id,id);
ALTER TABLE ai_suggestions ADD CONSTRAINT ai_suggestions_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE ai_suggestions ADD CONSTRAINT ai_suggestions_request_id_fk FOREIGN KEY (company_id,request_id) REFERENCES ai_requests(company_id,id);
ALTER TABLE ai_suggestions ADD CONSTRAINT ai_suggestions_scenario_id_fk FOREIGN KEY (company_id,scenario_id) REFERENCES scenarios(company_id,id);
ALTER TABLE import_jobs ADD CONSTRAINT import_jobs_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE import_jobs ADD CONSTRAINT import_jobs_created_by_fk FOREIGN KEY (company_id,created_by) REFERENCES people(company_id,id);
ALTER TABLE import_jobs ADD CONSTRAINT import_jobs_file_id_fk FOREIGN KEY (company_id,file_id) REFERENCES file_objects(company_id,id);
ALTER TABLE import_rows ADD CONSTRAINT import_rows_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE import_rows ADD CONSTRAINT import_rows_import_job_id_fk FOREIGN KEY (company_id,import_job_id) REFERENCES import_jobs(company_id,id);
ALTER TABLE audit_events ADD CONSTRAINT audit_events_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE audit_events ADD CONSTRAINT audit_events_actor_id_fk FOREIGN KEY (company_id,actor_id) REFERENCES people(company_id,id);
ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE idempotency_records ADD CONSTRAINT idempotency_records_company_id_fk FOREIGN KEY (company_id) REFERENCES companies(id);
ALTER TABLE idempotency_records ADD CONSTRAINT idempotency_records_actor_id_fk FOREIGN KEY (company_id,actor_id) REFERENCES people(company_id,id);

-- Stable identity and mutually exclusive writes.
CREATE UNIQUE INDEX uq_orders_number ON orders(company_id,code);
CREATE UNIQUE INDEX uq_terms_version ON order_terms_versions(company_id,order_id,version);
CREATE UNIQUE INDEX uq_route_version ON route_versions(company_id,order_line_id,version);
CREATE UNIQUE INDEX uq_plan_version ON plan_versions(company_id,order_id,version);
CREATE UNIQUE INDEX uq_membership ON memberships(company_id,person_id);
CREATE UNIQUE INDEX uq_production_idempotence ON production_events(company_id,idempotency_key);
CREATE UNIQUE INDEX uq_idempotence ON idempotency_records(company_id,actor_id,action_kind,request_key);
CREATE UNIQUE INDEX uq_import_row ON import_rows(company_id,import_job_id,sheet_name,row_no);
CREATE UNIQUE INDEX uq_batch_qr ON batches(company_id,qr_token) WHERE qr_token IS NOT NULL;
CREATE INDEX idx_orders_lookup ON orders(company_id,status);
CREATE INDEX idx_plan_allocations_lookup ON plan_allocations(company_id,resource_id,starts_at);
CREATE INDEX idx_assignments_lookup ON assignments(company_id,worker_id,state);
CREATE INDEX idx_production_events_lookup ON production_events(company_id,assignment_id,occurred_at);
CREATE INDEX idx_material_movements_lookup ON material_movements(company_id,material_id,occurred_at);
CREATE INDEX idx_audit_events_lookup ON audit_events(company_id,happened_at);
CREATE INDEX idx_outbox_events_lookup ON outbox_events(company_id,published_at);
CREATE INDEX idx_import_rows_lookup ON import_rows(company_id,import_job_id);
ALTER TABLE order_lines ADD CONSTRAINT chk_order_lines_qty CHECK (quantity > 0);
ALTER TABLE batches ADD CONSTRAINT chk_batches_qty CHECK (quantity > 0);
ALTER TABLE material_balances ADD CONSTRAINT chk_material_stock CHECK (available_qty >= 0 AND reserved_qty >= 0);
ALTER TABLE quality_checks ADD CONSTRAINT chk_quality_qty CHECK (accepted_qty >= 0 AND rework_qty >= 0 AND scrap_qty >= 0);
ALTER TABLE resources ADD CONSTRAINT chk_resource_capacity CHECK (quantity >= 0 AND capacity >= 0);

-- Permissions are always checked by API; database tenant keys prevent cross-company references.
