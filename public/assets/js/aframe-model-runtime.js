(function (root) {
  'use strict';

  function install(scene, options) {
    if (!scene || scene.__creatrAFrameModelRuntimeInstalled) return;
    scene.__creatrAFrameModelRuntimeInstalled = true;
    options = options || {};
    var three = root.AFRAME && root.AFRAME.THREE ? root.AFRAME.THREE : root.THREE;
    var report = typeof options.report === 'function' ? options.report : function () {};
    var fail = typeof options.fail === 'function' ? options.fail : function (message) {
      if (typeof root.showPieceError === 'function') root.showPieceError(message);
    };

    function sourceFor(entity) {
      var ref = String(entity.getAttribute('gltf-model') || '');
      if (ref.charAt(0) !== '#') return ref || '(missing gltf-model source)';
      var asset = scene.ownerDocument && scene.ownerDocument.getElementById(ref.slice(1));
      return String(asset && asset.getAttribute('src') || ref);
    }

    function emit(entity, status, data) {
      var detail = Object.assign({ status: status, entityId: entity.id || '' }, data || {});
      try { entity.dispatchEvent(new CustomEvent('creatr-model-status', { detail: detail })); } catch (_) {}
      try { root.parent && root.parent.postMessage(Object.assign({ type: 'creatr-aframe-model' }, detail), '*'); } catch (_) {}
      try { report(detail); } catch (_) {}
    }

    function fit(entity) {
      if (entity.__creatrModelFitted) return true;
      var source = sourceFor(entity);
      var mesh = entity.getObject3D && entity.getObject3D('mesh');
      if (!mesh || !three || !three.Box3 || !three.Vector3 || !three.Matrix4) return false;
      mesh.updateWorldMatrix && mesh.updateWorldMatrix(true, true);
      var box = new three.Box3().setFromObject(mesh);
      var size = box.getSize(new three.Vector3());
      var center = box.getCenter(new three.Vector3());
      var maxDim = Math.max(size.x, size.y, size.z);
      if (!isFinite(maxDim) || maxDim <= 0) {
        emit(entity, 'invalid', { source: source, message: 'The GLB loaded with empty bounds.', dimensions: [size.x, size.y, size.z] });
        return false;
      }
      center.applyMatrix4(new three.Matrix4().copy(mesh.matrixWorld).invert());
      mesh.position.sub(center);
      var fitScale = 3 / maxDim;
      if (isFinite(fitScale) && fitScale > 0) mesh.scale.multiplyScalar(fitScale);
      entity.__creatrModelFitted = true;
      emit(entity, 'loaded', {
        source: source,
        dimensions: [size.x, size.y, size.z],
        targetSize: 3,
        fitScale: fitScale,
        message: 'Loaded and fitted ' + source + '.',
      });
      return true;
    }

    function parseBinary(entity, source, done, failed, attempt) {
      attempt = attempt || 0;
      var component = entity.components && entity.components['gltf-model'];
      var loader = component && component.loader;
      if (!loader || typeof loader.parse !== 'function') {
        if (attempt < 20) {
          setTimeout(function () {
            parseBinary(entity, source, done, failed, attempt + 1);
          }, 250);
          return;
        }
        failed(new Error('A-Frame GLTFLoader.parse is unavailable.'));
        return;
      }
      root.fetch(source, { credentials: 'same-origin' }).then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.arrayBuffer();
      }).then(function (bytes) {
        return new Promise(function (resolve, reject) {
          var cut = source.search(/[?#]/);
          var clean = cut >= 0 ? source.slice(0, cut) : source;
          var basePath = clean.replace(/[^/]*$/, '');
          loader.parse(bytes, basePath, resolve, reject);
        });
      }).then(function (gltf) {
        var model = gltf && (gltf.scene || (gltf.scenes && gltf.scenes[0]));
        if (!model) throw new Error('The parsed GLB did not contain a scene.');
        entity.setObject3D('mesh', model);
        if (entity.components && entity.components['gltf-model']) entity.components['gltf-model'].model = model;
        done();
      }).catch(failed);
    }

    function watch(entity) {
      if (entity.__creatrAFrameModelWatched) return;
      entity.__creatrAFrameModelWatched = true;
      var source = sourceFor(entity);
      entity.addEventListener('model-loaded', function () { fit(entity); }, { once: true });
      entity.addEventListener('model-error', function (event) {
        recover(entity, source, event && event.detail && event.detail.message || 'The GLB could not be parsed or fetched.');
      }, { once: true });
      setTimeout(function () {
        if (fit(entity)) return;
        if (!/\.gltf(?:[?#]|$)/i.test(source)) recover(entity, source, 'A-Frame did not produce a model after the initial load attempt.');
      }, 500);
    }

    function recover(entity, source, initialMessage) {
      if (entity.__creatrBinaryModelRecoveryStarted) return;
      entity.__creatrBinaryModelRecoveryStarted = true;
      parseBinary(entity, source, function () {
        fit(entity);
        entity.emit('model-loaded', { format: 'gltf', model: entity.getObject3D('mesh') });
      }, function (error) {
        var message = 'A-Frame model ' + source + ' failed to load: ' + initialMessage + ' Binary fallback failed: ' + (error && error.message || error);
        emit(entity, 'error', { source: source, message: message });
        fail(message);
      });
    }

    function scan() {
      var nodes = scene.querySelectorAll ? scene.querySelectorAll('[gltf-model]') : [];
      for (var i = 0; i < nodes.length; i += 1) watch(nodes[i]);
    }

    scan();
    var attempts = 0;
    var timer = setInterval(function () {
      scan();
      attempts += 1;
      if (attempts >= 20) clearInterval(timer);
    }, 250);
  }

  root.CreatrAFrameModelRuntime = { install: install };
}(window));
