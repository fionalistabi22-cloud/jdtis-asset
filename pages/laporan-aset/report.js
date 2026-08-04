document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-report-filter]');
    if (!root) return;

    const wilayah = document.getElementById('wilayah_id');
    const daerah = document.getElementById('daerah_id');
    const agensi = document.getElementById('agensi_id');
    if (!daerah || !agensi) return;

    const fixedWilayah = Number(root.dataset.fixedWilayah || 0);
    const base = root.dataset.apiBase;

    function currentWilayah() {
        return wilayah ? Number(wilayah.value || 0) : fixedWilayah;
    }

    function loading(select, text) {
        select.disabled = true;
        select.innerHTML = '<option value="0">' + text + '</option>';
    }

    async function json(url) {
        const response = await fetch(url, {headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error('Gagal memuatkan data.');
        return response.json();
    }

    function fill(select, rows, idKey, nameKey, label) {
        select.innerHTML = '<option value="0">' + label + '</option>';
        rows.forEach(function (row) {
            const option = document.createElement('option');
            option.value = row[idKey];
            option.textContent = row[nameKey];
            select.appendChild(option);
        });
        select.disabled = false;
    }

    async function loadAgencies(regionId, districtId) {
        if (regionId <= 0) { loading(agensi, 'Pilih wilayah dahulu'); return; }
        if (regionId > 1 && districtId <= 0) { loading(agensi, 'Pilih daerah dahulu'); return; }
        loading(agensi, 'Memuatkan agensi...');
        try {
            const qs = new URLSearchParams({wilayah_id:regionId, daerah_id:districtId || 0});
            const payload = await json(base + '/api_agensi.php?' + qs);
            fill(agensi, payload.data || [], 'agensi_id', 'nama_agensi', 'Semua Agensi');
        } catch (error) { loading(agensi, 'Agensi gagal dimuatkan'); }
    }

    async function loadDistricts(regionId) {
        if (regionId <= 0) {
            loading(daerah, 'Pilih wilayah dahulu');
            loading(agensi, 'Pilih daerah dahulu');
            return;
        }
        if (regionId === 1) {
            loading(daerah, 'Tidak berkenaan - Ibu Pejabat');
            await loadAgencies(regionId, 0);
            return;
        }
        loading(daerah, 'Memuatkan daerah...');
        loading(agensi, 'Pilih daerah dahulu');
        try {
            const payload = await json(base + '/api_daerah.php?wilayah_id=' + encodeURIComponent(regionId));
            fill(daerah, payload.data || [], 'daerah_id', 'nama_daerah', 'Semua Daerah');
        } catch (error) { loading(daerah, 'Daerah gagal dimuatkan'); }
    }

    if (wilayah) wilayah.addEventListener('change', function () { loadDistricts(Number(this.value || 0)); });
    daerah.addEventListener('change', function () { loadAgencies(currentWilayah(), Number(this.value || 0)); });
});
