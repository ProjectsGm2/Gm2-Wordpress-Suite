const addPassive = !window.AE_PERF_DISABLE_PASSIVE && window.aePerf?.addPassive
    ? window.aePerf.addPassive
    : (el, type, handler, options) => el.addEventListener(type, handler, options);

addPassive(document, 'DOMContentLoaded', () => {
    const fields = document.querySelectorAll('.gm2-geo-field');
    fields.forEach(field => {
        const mapEl = field.querySelector('.gm2-geo-map');
        const latInput = field.querySelector('input[name$="[lat]"]');
        const lngInput = field.querySelector('input[name$="[lng]"]');
        const addrInput = field.querySelector('input[name$="[address]"]');
        const addrDisplay = field.querySelector('.gm2-geo-address');
        const lat = parseFloat(latInput.value) || 0;
        const lng = parseFloat(lngInput.value) || 0;
        const map = L.map(mapEl).setView([lat || 0, lng || 0], lat && lng ? 13 : 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        let marker = null;
        if (lat && lng) {
            marker = L.marker([lat, lng]).addTo(map);
        }
        function updateMarker(e) {
            const coord = e.latlng;
            latInput.value = coord.lat.toFixed(6);
            lngInput.value = coord.lng.toFixed(6);
            if (marker) {
                marker.setLatLng(coord);
            } else {
                marker = L.marker(coord).addTo(map);
            }
            fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + coord.lat + '&lon=' + coord.lng)
                .then(r => r.json())
                .then(data => {
                    addrInput.value = JSON.stringify(data.address || {});
                    const addr = [
                        data.address.road,
                        data.address.city || data.address.town || data.address.village,
                        data.address.state,
                        data.address.postcode,
                        data.address.country
                    ].filter(Boolean).join(', ');
                    addrDisplay.textContent = addr;
                });
        }
        map.on('click', updateMarker);
    });
});
