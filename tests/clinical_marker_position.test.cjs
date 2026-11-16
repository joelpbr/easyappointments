const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/components/clinical_record_modal.js', 'utf8');
const place = source.slice(source.indexOf('    function placeMarker(event)'), source.indexOf('    function editMarker(key)'));

for (const scale of [1, 0.55]) {
    test(`the placed marker centers on the pointer at scale ${scale}`, () => {
        // The image and overlay deliberately have different origins and dimensions.
        const rect = {left: 212, top: 93, width: 688 * scale, height: 535 * scale};
        const feet = {diagrams: [{id: 0}], markers: []};
        const context = {
            $feetPathologies: {find: () => ({data: () => 14})},
            $feetPicture: [{naturalWidth: 688, getBoundingClientRect: () => ({left: 190, top: 90, width: 700, height: 540})}],
            $feetMarkers: [{getBoundingClientRect: () => rect}],
            feet,
            diagramId: 0,
            MARKER_SCALE: 16 / 360,
            markerKey: 0,
            freshMarkerKey: null,
            originalSize: () => ({width: 688, height: 535}),
            sizeFeetPicture() {},
            renderMarkers() {},
            updateSaveButtons() {},
        };
        vm.createContext(context);
        vm.runInContext(place, context);
        for (const [x, y] of [[0.15, 0.2], [0.5, 0.5], [0.85, 0.8]]) {
            const pointer = {clientX: rect.left + x * rect.width, clientY: rect.top + y * rect.height};
            context.placeMarker(pointer);
            const marker = feet.markers.at(-1);
            const centerX = rect.left + ((marker.x1 + marker.x2) / 2 / 688) * rect.width;
            const centerY = rect.top + ((marker.y1 + marker.y2) / 2 / 535) * rect.height;
            assert.ok(Math.abs(centerX - pointer.clientX) <= 0.5);
            assert.ok(Math.abs(centerY - pointer.clientY) <= 0.5);
        }
    });
}
