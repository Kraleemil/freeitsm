/**
 * FreeITSM Asset Management — table view config
 *
 * Supplies the asset-specific pieces to the shared data-table engine
 * (assets/js/data-table.js): the COLUMNS catalogue and asset loading. The table
 * is read-only — clicking a row deep-links to the split-pane view for that
 * asset — and adds PDF export on top of the shared CSV. Everything else
 * (sort/filter/search/columns/preferences) is the shared engine.
 */
(function () {
    'use strict';

    const tt = (k, p) => (window.t ? window.t('asset-management.' + k, p) : k);

    const COLUMNS = [
        { key: 'hostname',          label: tt('table.col_hostname'),        type: 'string', defaultVisible: true,  defaultOrder: 0  },
        { key: 'asset_type_name',   label: tt('field.type'),                type: 'string', defaultVisible: true,  defaultOrder: 1  },
        { key: 'asset_status_name', label: tt('field.status'),              type: 'string', defaultVisible: true,  defaultOrder: 2  },
        { key: 'manufacturer',      label: tt('field.manufacturer'),        type: 'string', defaultVisible: true,  defaultOrder: 3  },
        { key: 'model',             label: tt('field.model'),               type: 'string', defaultVisible: true,  defaultOrder: 4  },
        { key: 'operating_system',  label: tt('table.col_os'),              type: 'string', defaultVisible: true,  defaultOrder: 5  },
        { key: 'feature_release',   label: tt('field.feature_release'),     type: 'string', defaultVisible: false, defaultOrder: 6  },
        { key: 'build_number',      label: tt('table.col_build'),           type: 'string', defaultVisible: false, defaultOrder: 7  },
        { key: 'service_tag',       label: tt('detail.service_tag'),        type: 'string', defaultVisible: false, defaultOrder: 8  },
        { key: 'cpu_name',          label: tt('field.cpu'),                 type: 'string', defaultVisible: false, defaultOrder: 9  },
        { key: 'speed',             label: tt('field.cpu_speed'),           type: 'number', defaultVisible: false, defaultOrder: 10 },
        { key: 'memory',            label: tt('field.memory'),              type: 'number', defaultVisible: false, defaultOrder: 11 },
        { key: 'bios_version',      label: tt('table.col_bios'),            type: 'string', defaultVisible: false, defaultOrder: 12 },
        { key: 'user_count',        label: tt('table.col_assigned_users'),  type: 'number', defaultVisible: true,  defaultOrder: 13 },
        { key: 'location_path',     label: tt('field.location'),            type: 'string', defaultVisible: true,  defaultOrder: 14 },
        { key: 'purchase_date',     label: tt('field.purchase_date'),       type: 'date',   defaultVisible: false, defaultOrder: 15 },
        { key: 'purchase_cost',     label: tt('table.col_cost'),            type: 'number', defaultVisible: false, defaultOrder: 16 },
        { key: 'supplier_name',     label: tt('field.supplier'),            type: 'string', defaultVisible: false, defaultOrder: 17 },
        { key: 'warranty_expiry',   label: tt('field.warranty_expiry'),     type: 'date',   defaultVisible: false, defaultOrder: 18 },
    ];

    /**
     * Custom field columns (docs/design/flexible-asset-fields.md §8).
     *
     * Handed over by table.php as a global rather than fetched here: the shared
     * engine boots on DOMContentLoaded, so an await before createDataTable()
     * would mean the event had already fired and the table never built.
     *
     * Hidden by default. Somebody who ticked "offer as a column" said it should
     * be AVAILABLE, not that everybody should have it forced on — the column
     * picker is where it gets turned on, and each analyst's choice is already
     * remembered.
     */
    (window.assetCustomColumns || []).forEach((c, i) => {
        COLUMNS.push({
            key: c.key,
            label: c.label,
            type: c.type,
            defaultVisible: false,
            defaultOrder: 100 + i,   // after every built-in, whatever gets added later
        });
    });

    createDataTable({
        accent: '#0078d4',
        prefApi: '../api/system/',
        prefKey: 'asset_table_v1',
        noun: 'asset',
        exportName: 'assets',
        defaultSort: { key: 'hostname', dir: 'asc' },
        columns: COLUMNS,
        // `asset_id`, NOT `asset` (issue #84). The split-pane view reads only
        // `asset_id` — the spelling includes/entity_links.php declares canonical
        // and the ticket inbox already uses — so `asset` opened the module and
        // then sat there with nothing selected, which reads as "the asset failed
        // to load" rather than as a bad link.
        onRowClick: row => { window.location.href = `index.php?asset_id=${row.id}`; },
        pdf: { title: tt('nav.assets'), headFill: [0, 120, 212], logo: '../assets/images/CompanyLogo.png' },

        load: async () => {
            const d = await fetch('../api/assets/get_assets.php').then(r => r.json());
            if (!d.success) { console.error('get_assets:', d.error); return []; }
            return d.assets || [];
        },
    });
})();
