(function(){
  'use strict';

  // Utility: initialize all fields within a context (document or newly rendered form)
  function initAll(context) {
    var containers = (context || document).querySelectorAll('.gfmb-field');
    containers.forEach(function(container){
      if (container.dataset.gfmbInitialized === '1') return;
      container.dataset.gfmbInitialized = '1';
      initField(container);
    });
  }

  // Initialize a single field instance
  function initField(container) {
    var cfg = window.GFMB_Config || { apiKeyPresent: false, i18n: {} };

    var mapEl      = container.querySelector('.gfmb-map');
    var postcodeEl = container.querySelector('.gfmb-postcode');
    var locateBtn  = container.querySelector('.gfmb-locate');
    var undoBtn    = container.querySelector('.gfmb-undo');
    var clearBtn   = container.querySelector('.gfmb-clear');
    var drawingControlsEl = container.querySelector('.gfmb-drawing-controls');
    var hiddenEl   = container.querySelector('.gfmb-encoded-path');
        var centerLatEl = container.querySelector('.gfmb-center-lat');
        var centerLngEl = container.querySelector('.gfmb-center-lng');
        var zoomEl      = container.querySelector('.gfmb-zoom');

        // Read any pre-set center/zoom coming from server (after validation error)
        var ds = container.dataset || {};
        var presetCenter = null;
        var presetZoom = null;
        if (ds.centerLat && ds.centerLng) {
          var clat = parseFloat(ds.centerLat);
          var clng = parseFloat(ds.centerLng);
          if (!isNaN(clat) && !isNaN(clng)) {
            presetCenter = { lat: clat, lng: clng };
          }
        }
        if (ds.zoom) {
          var z = parseInt(ds.zoom, 10);
          if (!isNaN(z)) presetZoom = z;
        }

    if (!cfg.apiKeyPresent || typeof google === 'undefined' || !google.maps) {
      // Disable controls if API not available
      [postcodeEl, locateBtn, undoBtn, clearBtn].forEach(function(el){ if (el) el.disabled = true; });
      if (mapEl) mapEl.innerHTML = '<div class="gfmb-warning">' + (cfg.i18n && cfg.i18n.noApiKey ? cfg.i18n.noApiKey : 'Google Maps API key missing.') + '</div>';
      return;
    }

    var defaultCenter = { lat: 51.509865, lng: -0.118092 }; // London fallback
    var map = new google.maps.Map(mapEl, {
      center: defaultCenter,
      zoom: 22,
      mapTypeId: 'hybrid',
      clickableIcons: false,
      gestureHandling: 'cooperative'
    });

    var geocoder = new google.maps.Geocoder();
    var drawingManager = new google.maps.drawing.DrawingManager({
      drawingMode: google.maps.drawing.OverlayType.POLYGON,
      drawingControl: false,
      drawingControlOptions: {
        position: google.maps.ControlPosition.TOP_CENTER,
        drawingModes: ['polygon']
      },
      polygonOptions: {
        fillColor: '#FF0000',
        fillOpacity: 0.27,
        strokeColor: '#FF0000',
        strokeOpacity: 1,
        strokeWeight: 2,
        clickable: true,
        editable: true,
        zIndex: 1
      }
    });
    drawingManager.setMap(map);

    function saveViewportToHidden(){
      if (!centerLatEl || !centerLngEl || !zoomEl) return;
      var c = map.getCenter();
      if (c) {
        centerLatEl.value = c.lat().toFixed(6);
        centerLngEl.value = c.lng().toFixed(6);
      }
      zoomEl.value = String(map.getZoom() || '');
    }

    function setLocatedState(located){
      if (located) {
        // Show map and enable drawing controls
        mapEl.style.display = '';
        if (drawingControlsEl) drawingControlsEl.style.display = '';
        if (undoBtn) undoBtn.style.display = '';
        if (clearBtn) clearBtn.style.display = '';
        drawingManager.setOptions({ drawingControl: true });
        google.maps.event.trigger(map, 'resize');
        // ensure map recenters to avoid blank tiles after show
        var c = map.getCenter();
        if (c) map.setCenter(c);
        saveViewportToHidden();
      } else {
        // Hide map and disable drawing controls
        drawingManager.setOptions({ drawingControl: false });
        mapEl.style.display = 'none';
        if (drawingControlsEl) drawingControlsEl.style.display = 'none';
        if (undoBtn) undoBtn.style.display = 'none';
        if (clearBtn) clearBtn.style.display = 'none';
      }
    }

    var polygon = null;

    function updateHiddenFromPolygon() {
      if (!polygon) { hiddenEl.value = ''; return; }
      var path = polygon.getPath();
      if (!path || path.getLength() < 3) {
        hiddenEl.value = '';
        return;
      }
      if (google.maps.geometry && google.maps.geometry.encoding) {
        var encoded = google.maps.geometry.encoding.encodePath(path);
        hiddenEl.value = encoded;
      }
    }

    function setButtonsState() {
      var hasPoly = !!polygon && polygon.getPath() && polygon.getPath().getLength() > 0;
      undoBtn.disabled = !hasPoly;
      clearBtn.disabled = !hasPoly;
    }

    function attachPathListeners(poly) {
      var path = poly.getPath();
      path.addListener('set_at', updateHiddenFromPolygon);
      path.addListener('insert_at', updateHiddenFromPolygon);
      path.addListener('remove_at', updateHiddenFromPolygon);
    }

    google.maps.event.addListener(drawingManager, 'overlaycomplete', function(evt){
      if (evt.type !== google.maps.drawing.OverlayType.POLYGON) return;
      // Remove old polygon if any
      if (polygon) {
        polygon.setMap(null);
      }
      polygon = evt.overlay;
      drawingManager.setDrawingMode(null);
      // Ensure editable
      polygon.setEditable(true);
      attachPathListeners(polygon);
      updateHiddenFromPolygon();
      setButtonsState();
    });

    // Restore from saved state if available
    (function restoreState(){
      var savedPath = (hiddenEl && hiddenEl.value ? hiddenEl.value.trim() : '');
      var hasSavedPath = savedPath.length > 0;
      var hadPresetView = !!presetCenter || (presetZoom !== null && presetZoom !== undefined);

      if (hasSavedPath && google.maps.geometry && google.maps.geometry.encoding) {
        try {
          var decoded = google.maps.geometry.encoding.decodePath(savedPath);
          if (decoded && decoded.length >= 3) {
            // Create polygon
            polygon = new google.maps.Polygon({
              paths: decoded,
              fillColor: '#FF0000',
              fillOpacity: 0.27,
              strokeColor: '#FF0000',
              strokeOpacity: 1,
              strokeWeight: 2,
              editable: true,
              map: map
            });
            attachPathListeners(polygon);
            updateHiddenFromPolygon();
            setButtonsState();
            if (hadPresetView && presetCenter) {
              map.setCenter(presetCenter);
              if (presetZoom) map.setZoom(presetZoom);
            } else {
              var b = new google.maps.LatLngBounds();
              decoded.forEach(function(pt){ b.extend(pt); });
              if (!b.isEmpty()) {
                map.fitBounds(b);
                // Zoom in further on the drawn polygon
                var z2 = map.getZoom();
                if (typeof z2 === 'number') {
                  map.setZoom(Math.min(22, z2 + 5));
                }
              }
            }
          }
        } catch(e){}
      } else if (presetCenter) {
        map.setCenter(presetCenter);
        if (presetZoom) map.setZoom(presetZoom);
      }

      // Determine initial visibility
      var shouldShow = hasSavedPath || !!presetCenter;
      setLocatedState(shouldShow);
    })();

    // Keep center/zoom in sync
    map.addListener('idle', saveViewportToHidden);

    locateBtn.addEventListener('click', function(){
      var val = (postcodeEl.value || '').trim();
      if (!val) return;
      geocoder.geocode({ address: val }, function(results, status){
        if (status === 'OK' && results && results[0]) {
          var res = results[0];
          if (res.geometry && res.geometry.viewport) {
            map.fitBounds(res.geometry.viewport);
            // Zoom in further for a closer view
            var z = map.getZoom();
            if (typeof z === 'number') {
              map.setZoom(Math.min(15, z + 5));
            }
          } else if (res.geometry && res.geometry.location) {
            map.setCenter(res.geometry.location);
            map.setZoom(15);
          }
          setLocatedState(true);
          saveViewportToHidden();
        } else {
          alert((cfg.i18n && cfg.i18n.geocodeFailed) || 'Geocoding failed.');
        }
      });
    });

    undoBtn.addEventListener('click', function(){
      if (!polygon) return;
      var path = polygon.getPath();
      var len = path.getLength();
      if (len > 0) {
        path.removeAt(len - 1);
      }
      updateHiddenFromPolygon();
      setButtonsState();
    });

    clearBtn.addEventListener('click', function(){
      if (polygon) {
        polygon.setMap(null);
        polygon = null;
      }
      hiddenEl.value = '';
      drawingManager.setDrawingMode(google.maps.drawing.OverlayType.POLYGON);
      setButtonsState();
    });

    setButtonsState();
  }

  // When GF renders forms via AJAX, hook into gform_post_render
  document.addEventListener('DOMContentLoaded', function(){ initAll(document); });
  if (window.jQuery) {
    jQuery(document).on('gform_post_render', function(event, form_id){
      initAll(document.getElementById('gform_' + form_id) || document);
    });
  }
})();
