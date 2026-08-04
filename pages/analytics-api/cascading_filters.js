/**
 * JTDIS cascading analytical filters.
 *
 * Required IDs:
 * - wilayah_id (optional for fixed-region dashboards)
 * - daerah_id
 * - agensi_id
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-cascading-filter]');

    if (!root) {
        return;
    }

    const region = document.getElementById('wilayah_id');
    const district = document.getElementById('daerah_id');
    const agency = document.getElementById('agensi_id');

    if (!district || !agency) {
        return;
    }

    const fixedRegionId = Number(root.dataset.fixedWilayah || 0);
    const apiBase = root.dataset.apiBase || '/jdtis_asset/pages/analytics-api';

    const selectedDistrict = Number(root.dataset.selectedDaerah || 0);
    const selectedAgency = Number(root.dataset.selectedAgensi || 0);

    const currentRegionId = () => {
        return region ? Number(region.value || 0) : fixedRegionId;
    };

    const setLoading = (select, label) => {
        select.disabled = true;
        select.innerHTML = `<option value="0">${label}</option>`;
    };

    const setOptions = (select, rows, valueKey, labelKey, selectedValue, emptyLabel) => {
        select.innerHTML = `<option value="0">${emptyLabel}</option>`;

        rows.forEach((row) => {
            const option = document.createElement('option');
            option.value = row[valueKey];
            option.textContent = row[labelKey];

            if (Number(option.value) === Number(selectedValue)) {
                option.selected = true;
            }

            select.appendChild(option);
        });

        select.disabled = false;
    };

    const fetchJson = async (url) => {
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) {
            throw new Error('Permintaan data gagal.');
        }

        return response.json();
    };

    const loadAgencies = async (regionId, districtId, selected = 0) => {
        if (regionId <= 0) {
            setLoading(agency, 'Pilih wilayah dahulu');
            return;
        }

        if (regionId > 1 && districtId <= 0) {
            setLoading(agency, 'Pilih daerah dahulu');
            return;
        }

        setLoading(agency, 'Memuatkan agensi...');

        try {
            const query = new URLSearchParams({
                wilayah_id: String(regionId),
                daerah_id: String(districtId || 0)
            });

            const payload = await fetchJson(`${apiBase}/agensi.php?${query}`);

            setOptions(
                agency,
                payload.data || [],
                'agensi_id',
                'nama_agensi',
                selected,
                'Semua Agensi'
            );
        } catch (error) {
            setLoading(agency, 'Agensi gagal dimuatkan');
        }
    };

    const loadDistricts = async (
        regionId,
        selectedDistrictValue = 0,
        selectedAgencyValue = 0
    ) => {
        if (regionId <= 0) {
            setLoading(district, 'Pilih wilayah dahulu');
            setLoading(agency, 'Pilih daerah dahulu');
            return;
        }

        if (regionId === 1) {
            district.innerHTML = '<option value="0">Tidak berkenaan — Ibu Pejabat</option>';
            district.disabled = true;
            await loadAgencies(regionId, 0, selectedAgencyValue);
            return;
        }

        setLoading(district, 'Memuatkan daerah...');
        setLoading(agency, 'Pilih daerah dahulu');

        try {
            const query = new URLSearchParams({
                wilayah_id: String(regionId)
            });

            const payload = await fetchJson(`${apiBase}/daerah.php?${query}`);

            setOptions(
                district,
                payload.data || [],
                'daerah_id',
                'nama_daerah',
                selectedDistrictValue,
                'Semua Daerah'
            );

            if (selectedDistrictValue > 0) {
                await loadAgencies(
                    regionId,
                    selectedDistrictValue,
                    selectedAgencyValue
                );
            }
        } catch (error) {
            setLoading(district, 'Daerah gagal dimuatkan');
            setLoading(agency, 'Pilih daerah dahulu');
        }
    };

    if (region) {
        region.addEventListener('change', async () => {
            await loadDistricts(Number(region.value || 0), 0, 0);
        });
    }

    district.addEventListener('change', async () => {
        await loadAgencies(
            currentRegionId(),
            Number(district.value || 0),
            0
        );
    });

    // The server already renders current values. This repairs the dropdowns
    // when the browser restores a stale form or when JavaScript loads late.
    const regionId = currentRegionId();

    if (
        regionId > 0
        && district.options.length <= 1
        && (
            selectedDistrict > 0
            || regionId === 1
        )
    ) {
        loadDistricts(regionId, selectedDistrict, selectedAgency);
    }
});
