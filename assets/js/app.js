(function () {
  'use strict';

  /** Normaliza path+r (+ filtros estables) para comparar scroll memory entre redirects. */
  function pageKeyFromUrl(href) {
    try {
      var u = href ? new URL(href, location.origin) : new URL(location.href);
      var r = u.searchParams.get('r') || '';
      if (r.charAt(0) !== '/' && r !== '') {
        r = '/' + r;
      }
      var parts = [];
      if (r) {
        parts.push('r=' + r);
      }
      ['year', 'area', 'done', 'status', 'priority', 'q'].forEach(function (key) {
        var val = u.searchParams.get(key);
        if (val !== null && val !== '') {
          parts.push(key + '=' + val);
        }
      });
      return u.pathname + (parts.length ? '?' + parts.join('&') : '');
    } catch (e) {
      return location.pathname + location.search;
    }
  }

  function applySystemTheme() {
    var root = document.documentElement;
    var pref = root.getAttribute('data-theme-pref') || root.getAttribute('data-theme') || 'light';
    if (pref !== 'system') {
      return;
    }
    var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
  }

  function initTheme() {
    applySystemTheme();
    if (!window.matchMedia) {
      return;
    }
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var handler = function () {
      applySystemTheme();
    };
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', handler);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(handler);
    }
  }

  function initThemeToggle() {
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var root = document.documentElement;
        var current = root.getAttribute('data-theme') || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        root.setAttribute('data-theme-pref', next);

        var url = btn.getAttribute('data-theme-url');
        var csrf = btn.getAttribute('data-csrf') || csrfToken();
        if (!url) {
          return;
        }
        var body = new FormData();
        body.append('_csrf', csrf);
        body.append('r', '/settings/theme');
        body.append('theme', next);
        fetch(url, {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        }).catch(function () {});
      });
    });
  }

  function appIndex() {
    return document.body.getAttribute('data-app-index') || 'index.php';
  }

  /** Pone la ruta en input[name=r] y el action solo en index.php (fiable en modales). */
  function setFormRoute(form, path) {
    if (!form) {
      return null;
    }
    path = String(path || '/');
    if (path.charAt(0) !== '/') {
      path = '/' + path;
    }
    form.action = appIndex();
    var r = form.querySelector('input[name="r"]');
    if (!r) {
      r = document.createElement('input');
      r.type = 'hidden';
      r.name = 'r';
      form.insertBefore(r, form.firstChild);
    }
    r.value = path;
    if (r.id) {
      /* keep id */
    }
    return form;
  }

  function routeAction(path) {
    path = String(path || '/');
    if (path.charAt(0) !== '/') {
      path = '/' + path;
    }
    return appIndex() + '?r=' + encodeURIComponent(path);
  }

  function csrfToken() {
    var el = document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function initHabitToggles() {
    document.querySelectorAll('[data-habit-toggle]').forEach(function (form) {
      var checkbox = form.querySelector('input[type="checkbox"]');
      if (!checkbox) {
        return;
      }
      checkbox.addEventListener('change', function () {
        var statusInput = form.querySelector('input[name="status"]');
        if (statusInput) {
          statusInput.value = checkbox.checked ? 'completed' : 'missed';
        }

        var body = new FormData(form);
        fetch(form.action || appIndex(), {
          method: 'POST',
          body: body,
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        })
          .then(function (res) {
            if (!res.ok) {
              throw new Error('toggle failed');
            }
            return res.json().catch(function () {
              return { ok: true };
            });
          })
          .then(function () {
            var row = form.closest('.habit-row');
            if (row) {
              row.classList.toggle('is-done', checkbox.checked);
            }
          })
          .catch(function () {
            checkbox.checked = !checkbox.checked;
            if (statusInput) {
              statusInput.value = checkbox.checked ? 'completed' : 'missed';
            }
          });
      });
    });
  }

  function updateDayBanner(dayRoot, dayStats) {
    if (!dayRoot || !dayStats) {
      return;
    }
    var pct = dayRoot.querySelector('[data-day-pct]');
    if (pct) {
      pct.textContent = String(dayStats.done) + '/' + String(dayStats.total);
    }
    var banner = dayRoot.querySelector('[data-day-banner]');
    if (banner) {
      banner.hidden = !dayStats.complete;
    }
    dayRoot.classList.toggle('is-complete', !!dayStats.complete);
  }

  function initTaskToggles() {
    document.querySelectorAll('[data-task-toggle]').forEach(function (form) {
      var checkbox = form.querySelector('input[type="checkbox"]');
      if (!checkbox) {
        return;
      }
      checkbox.addEventListener('change', function () {
        var statusInput = form.querySelector('input[name="status"]');
        if (statusInput) {
          statusInput.value = checkbox.checked ? 'completed' : 'pending';
        }
        var body = new FormData(form);
        fetch(appIndex(), {
          method: 'POST',
          body: body,
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        })
          .then(function (res) {
            if (!res.ok) {
              throw new Error('toggle failed');
            }
            return res.json();
          })
          .then(function (data) {
            var row = form.closest('.weekly-task-row, .habit-row');
            if (row) {
              row.classList.toggle('is-done', checkbox.checked);
            }
            var dayRoot = form.closest('[data-day-date]');
            if (data && data.day) {
              updateDayBanner(dayRoot, data.day);
            }
          })
          .catch(function () {
            checkbox.checked = !checkbox.checked;
            if (statusInput) {
              statusInput.value = checkbox.checked ? 'completed' : 'pending';
            }
          });
      });
    });
  }

  function initWeeklyTaskModal() {
    var dialog = document.getElementById('weeklyTaskModal');
    var form = document.getElementById('weeklyTaskForm');
    var route = document.getElementById('weeklyTaskRoute');
    if (!dialog || !form || !route) {
      return;
    }
    document.querySelectorAll('[data-edit-task]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id') || '0';
        route.value = '/weekly/' + id;
        setFormRoute(form, '/weekly/' + id);
        var title = document.getElementById('weeklyTaskTitle');
        var date = document.getElementById('weeklyTaskDate');
        var notes = document.getElementById('weeklyTaskNotes');
        if (title) {
          title.value = btn.getAttribute('data-title') || '';
        }
        if (date) {
          date.value = btn.getAttribute('data-date') || '';
        }
        if (notes) {
          notes.value = btn.getAttribute('data-notes') || '';
        }
        if (typeof dialog.showModal === 'function') {
          dialog.showModal();
        }
      });
    });
  }

  function initCharts() {
    if (typeof Chart === 'undefined') {
      return;
    }
    document.querySelectorAll('script[data-chart]').forEach(function (script) {
      var id = script.getAttribute('data-chart');
      var canvas = id ? document.getElementById(id) : null;
      if (!canvas) {
        return;
      }
      // Un canvas dentro de un <details> cerrado mide 0: lo dibujamos al abrirlo.
      var details = canvas.closest('details');
      if (details && !details.open) {
        details.addEventListener('toggle', function once() {
          if (details.open) {
            details.removeEventListener('toggle', once);
            initCharts();
          }
        });
        return;
      }
      try {
        var config = JSON.parse(script.textContent || '{}');
        var type = config.type || 'bar';
        var data = config.data || config;
        var options = Object.assign({}, config.options || {});
        var box = canvas.closest('.chart-box');
        if (!box) {
          box = document.createElement('div');
          box.className = 'chart-box';
          canvas.parentNode.insertBefore(box, canvas);
          box.appendChild(canvas);
        }
        options.responsive = true;
        options.maintainAspectRatio = false;
        options.resizeDelay = 50;
        if (!options.plugins) {
          options.plugins = {};
        }
        if (typeof Chart.getChart === 'function') {
          var existing = Chart.getChart(canvas);
          if (existing) {
            existing.destroy();
          }
        }
        new Chart(canvas, { type: type, data: data, options: options });
      } catch (err) {
        console.warn('Chart init failed', id, err);
      }
    });
  }

  function initSidebar() {
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!toggle || !sidebar) {
      return;
    }

    function close() {
      sidebar.classList.remove('is-open');
      if (backdrop) {
        backdrop.hidden = true;
      }
    }

    function open() {
      sidebar.classList.add('is-open');
      if (backdrop) {
        backdrop.hidden = false;
      }
    }

    toggle.addEventListener('click', function () {
      if (sidebar.classList.contains('is-open')) {
        close();
      } else {
        open();
      }
    });

    if (backdrop) {
      backdrop.addEventListener('click', close);
    }
  }

  function fillGoalEditModal(source) {
    var dialog = document.getElementById('goalEditModal');
    var form = document.getElementById('goalEditForm');
    var route = document.getElementById('editRoute');
    var deleteRoute = document.getElementById('deleteRoute');
    var monthRoute = document.getElementById('monthRoute');
    var binaryRoute = document.getElementById('binaryRoute');
    var unitsRoute = document.getElementById('unitsRoute');
    var monthGrid = document.getElementById('editMonthGrid');
    if (!dialog || !form || !source) {
      return null;
    }

    var id = source.getAttribute('data-id') || '';
    if (route) {
      route.value = '/goals/' + id;
    }
    setFormRoute(form, '/goals/' + id);
    if (deleteRoute) {
      deleteRoute.value = '/goals/' + id + '/delete';
    }
    var deleteForm = document.getElementById('goalDeleteForm');
    if (deleteForm) {
      setFormRoute(deleteForm, '/goals/' + id + '/delete');
    }
    if (monthRoute) {
      monthRoute.value = '/goals/' + id + '/months';
    }
    var monthForm = document.getElementById('goalMonthToggleForm');
    if (monthForm) {
      setFormRoute(monthForm, '/goals/' + id + '/months');
    }
    if (binaryRoute) {
      binaryRoute.value = '/goals/' + id + '/binary';
    }
    var binaryForm = document.getElementById('goalBinaryToggleForm');
    if (binaryForm) {
      setFormRoute(binaryForm, '/goals/' + id + '/binary');
    }
    if (unitsRoute) {
      unitsRoute.value = '/goals/' + id + '/units';
    }
    var unitsForm = document.getElementById('goalUnitsForm');
    if (unitsForm) {
      setFormRoute(unitsForm, '/goals/' + id + '/units');
    }
    if (monthGrid) {
      monthGrid.setAttribute('data-goal-id', id);
    }

    var map = {
      editTitle: 'data-title',
      editDescription: 'data-description',
      editAreaId: 'data-area-id',
      editPeriodYear: 'data-period-year',
      editStatus: 'data-status',
      editPriority: 'data-priority',
      editDueDate: 'data-due-date',
      editNextAction: 'data-next-action',
      editSuccessCriteria: 'data-success-criteria',
      editProgressMode: 'data-progress-mode',
    };

    Object.keys(map).forEach(function (fieldId) {
      var el = document.getElementById(fieldId);
      if (!el) {
        return;
      }
      var value = source.getAttribute(map[fieldId]);
      el.value = value == null ? '' : value;
    });

    var mode = normalizeProgressMode(source.getAttribute('data-progress-mode'));
    var modeSelect = document.getElementById('editProgressMode');
    if (modeSelect) {
      modeSelect.value = mode;
    }

    var currentInput = document.getElementById('editCurrentValue');
    var targetInput = document.getElementById('editTargetValue');
    if (currentInput) {
      currentInput.value = source.getAttribute('data-current-value') || '0';
    }
    if (targetInput) {
      targetInput.value = source.getAttribute('data-target-value') || '12';
    }
    setUnitsUnit(source.getAttribute('data-unit') || 'unidades');
    updateUnitsLabel();

    syncGoalProgressUi(mode, parseFloat(source.getAttribute('data-progress') || '0') >= 100);

    var monthsCsv = source.getAttribute('data-months') || '';
    var checked = {};
    monthsCsv.split(',').forEach(function (part) {
      var n = parseInt(part, 10);
      if (n >= 1 && n <= 12) {
        checked[n] = true;
      }
    });
    applyMonthChecks(checked);

    return dialog;
  }

  function normalizeProgressMode(mode) {
    return mode === 'binary' || mode === 'quantity' ? mode : 'months';
  }

  var unitsUnit = 'unidades';

  function setUnitsUnit(unit) {
    unitsUnit = unit || 'unidades';
    var hint = document.getElementById('editUnitsUnit');
    if (hint) {
      hint.textContent = unitsUnit;
    }
  }

  function updateUnitsLabel() {
    var label = document.getElementById('editUnitsLabel');
    var currentInput = document.getElementById('editCurrentValue');
    var targetInput = document.getElementById('editTargetValue');
    if (!label || !currentInput || !targetInput) {
      return;
    }
    var current = parseFloat(currentInput.value) || 0;
    var target = parseFloat(targetInput.value) || 0;
    var percent = target > 0 ? Math.min(100, Math.round((current / target) * 100)) : 0;
    label.textContent = percent + '% · ' + current + '/' + target + ' ' + unitsUnit;
  }

  /** Campos en bloques ocultos no deben validar ni viajar en el POST. */
  function setBlockFieldsEnabled(block, enabled) {
    if (!block) {
      return;
    }
    block.querySelectorAll('input, select, textarea, button').forEach(function (el) {
      if (el.hasAttribute('data-keep-enabled')) {
        return;
      }
      el.disabled = !enabled;
    });
  }

  function syncGoalProgressUi(mode, binaryDone) {
    mode = normalizeProgressMode(mode);
    var monthsBlock = document.getElementById('editMonthsBlock');
    var binaryBlock = document.getElementById('editBinaryBlock');
    var unitsBlock = document.getElementById('editUnitsBlock');
    var binaryBtn = document.getElementById('editBinaryToggle');
    var binaryLabel = document.getElementById('editBinaryLabel');
    if (monthsBlock) {
      monthsBlock.hidden = mode !== 'months';
      setBlockFieldsEnabled(monthsBlock, mode === 'months');
    }
    if (binaryBlock) {
      binaryBlock.hidden = mode !== 'binary';
      setBlockFieldsEnabled(binaryBlock, mode === 'binary');
    }
    if (unitsBlock) {
      unitsBlock.hidden = mode !== 'quantity';
      setBlockFieldsEnabled(unitsBlock, mode === 'quantity');
      if (mode === 'quantity') {
        var targetInput = document.getElementById('editTargetValue');
        var currentInput = document.getElementById('editCurrentValue');
        if (targetInput) {
          var t = parseFloat(targetInput.value);
          if (!isFinite(t) || t < 1) {
            targetInput.value = '12';
          }
        }
        if (currentInput && currentInput.value === '') {
          currentInput.value = '0';
        }
      }
    }
    if (binaryLabel) {
      binaryLabel.textContent = binaryDone ? '100% · Hecho' : '0% · Pendiente';
    }
    if (binaryBtn) {
      binaryBtn.textContent = binaryDone ? 'Marcar como pendiente' : 'Marcar como hecho';
      binaryBtn.setAttribute('data-done', binaryDone ? '1' : '0');
    }
  }

  function applyMonthChecks(checkedMap, gridEl, labelEl) {
    var grid = gridEl || document.getElementById('editMonthGrid');
    var label = labelEl || document.getElementById('editProgressLabel');
    if (!grid) {
      return;
    }
    var count = 0;
    grid.querySelectorAll('input[data-month]').forEach(function (input) {
      var m = parseInt(input.getAttribute('data-month'), 10);
      var on = !!checkedMap[m];
      input.checked = on;
      if (on) {
        count += 1;
      }
      var chip = input.closest('.month-chip');
      if (chip) {
        chip.classList.toggle('is-on', on);
      }
    });
    if (label) {
      label.textContent = Math.round((count / 12) * 100) + '% · ' + count + '/12';
    }
  }

  function initGoalMonthToggles() {
    var grid = document.getElementById('editMonthGrid');
    var monthForm = document.getElementById('goalMonthToggleForm');
    if (!grid || !monthForm) {
      return;
    }

    grid.querySelectorAll('input[data-month]').forEach(function (input) {
      input.addEventListener('change', function () {
        var goalId = grid.getAttribute('data-goal-id') || '';
        var month = input.getAttribute('data-month') || '';
        if (!goalId || !month) {
          return;
        }

        var csrf = monthForm.querySelector('input[name="_csrf"]');
        var body = new FormData();
        body.append('_csrf', csrf ? csrf.value : '');
        body.append('r', '/goals/' + goalId + '/months');
        body.append('month', month);

        fetch(monthForm.getAttribute('action') || window.location.pathname, {
          method: 'POST',
          body: body,
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        })
          .then(function (res) {
            return res.json();
          })
          .then(function (data) {
            if (!data || !data.ok || !data.progress) {
              input.checked = !input.checked;
              return;
            }
            applyMonthChecks(data.progress.months || {});
            var row = document.querySelector('[data-edit-goal][data-id="' + goalId + '"]');
            if (row) {
              var months = data.progress.months || {};
              var csv = [];
              Object.keys(months).forEach(function (k) {
                if (months[k]) {
                  csv.push(k);
                }
              });
              row.setAttribute('data-months', csv.join(','));
              row.setAttribute('data-progress', String(Math.round(data.progress.percent || 0)));
              var bar = row.querySelector('.progress > span');
              if (bar) {
                bar.style.width = Math.min(100, data.progress.percent || 0) + '%';
              }
              var pct = row.querySelector('.small');
              if (pct) {
                pct.textContent = Math.round(data.progress.percent || 0) + '%';
              }
              var tiny = row.querySelector('.tiny');
              if (tiny) {
                tiny.textContent = (data.progress.checked || 0) + '/12';
              }
            }
          })
          .catch(function () {
            input.checked = !input.checked;
          });
      });
    });
  }

  function initGoalEditModal() {
    document.addEventListener('click', function (event) {
      var row = event.target.closest('[data-edit-goal]');
      if (!row) {
        return;
      }
      if (event.target.closest('a, button, label, input, select, textarea, form')) {
        return;
      }
      var dialog = fillGoalEditModal(row);
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      var row = event.target.closest('[data-edit-goal]');
      if (!row || event.target !== row) {
        return;
      }
      event.preventDefault();
      var dialog = fillGoalEditModal(row);
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
      }
    });

    var auto = document.getElementById('goalEditModal');
    if (auto && auto.getAttribute('data-auto-open') === '1') {
      fillGoalEditModal(auto);
      if (typeof auto.showModal === 'function') {
        auto.showModal();
      }
    }

    var modeSelect = document.getElementById('editProgressMode');
    if (modeSelect) {
      modeSelect.addEventListener('change', function () {
        var done = parseFloat((document.getElementById('editBinaryLabel') || {}).textContent || '0') >= 100;
        var btn = document.getElementById('editBinaryToggle');
        if (btn) {
          done = btn.getAttribute('data-done') === '1';
        }
        syncGoalProgressUi(modeSelect.value, done);
      });
    }

    initGoalMonthToggles();
    initGoalBinaryToggle();
    initGoalUnits();
    initGoalCreateMode();
  }

  function initGoalCreateMode() {
    var mode = document.getElementById('createProgressMode');
    var targetField = document.getElementById('createTargetField');
    if (!mode) {
      return;
    }
    var sync = function () {
      var isQty = mode.value === 'quantity';
      if (targetField) {
        targetField.hidden = !isQty;
        setBlockFieldsEnabled(targetField, isQty);
        if (isQty) {
          var input = targetField.querySelector('input[name="target_value"]');
          if (input) {
            var t = parseFloat(input.value);
            if (!isFinite(t) || t < 1) {
              input.value = '12';
            }
          }
        }
      }
    };
    mode.addEventListener('change', sync);
    sync();
  }

  function initGoalUnits() {
    var block = document.getElementById('editUnitsBlock');
    var form = document.getElementById('goalUnitsForm');
    var currentInput = document.getElementById('editCurrentValue');
    var targetInput = document.getElementById('editTargetValue');
    var plusBtn = document.getElementById('editUnitsPlus');
    var saveBtn = document.getElementById('editUnitsSave');
    if (!block || !form || !currentInput || !targetInput) {
      return;
    }

    [currentInput, targetInput].forEach(function (input) {
      input.addEventListener('input', updateUnitsLabel);
    });

    function submitUnits(current) {
      var grid = document.getElementById('editMonthGrid');
      var goalId = grid ? grid.getAttribute('data-goal-id') : '';
      if (!goalId) {
        return;
      }
      var csrf = form.querySelector('input[name="_csrf"]');
      var body = new FormData();
      body.append('_csrf', csrf ? csrf.value : '');
      body.append('r', '/goals/' + goalId + '/units');
      body.append('current_value', String(current));
      body.append('target_value', targetInput.value || '12');

      fetch(form.getAttribute('action') || window.location.pathname, {
        method: 'POST',
        body: body,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (!data || !data.ok || !data.progress) {
            return;
          }
          currentInput.value = data.progress.current;
          targetInput.value = data.progress.target;
          setUnitsUnit(data.progress.unit);
          updateUnitsLabel();

          var statusEl = document.getElementById('editStatus');
          if (statusEl && data.progress.status) {
            statusEl.value = data.progress.status;
          }

          var row = document.querySelector('[data-edit-goal][data-id="' + goalId + '"]');
          if (row) {
            row.setAttribute('data-current-value', String(data.progress.current));
            row.setAttribute('data-target-value', String(data.progress.target));
            row.setAttribute('data-unit', data.progress.unit || '');
            row.setAttribute('data-progress', String(Math.round(data.progress.percent || 0)));
            row.setAttribute('data-progress-mode', 'quantity');
            var bar = row.querySelector('.progress > span');
            if (bar) {
              bar.style.width = Math.min(100, data.progress.percent || 0) + '%';
            }
            var pct = row.querySelector('.small');
            if (pct) {
              pct.textContent = Math.round(data.progress.percent || 0) + '%';
            }
            var tiny = row.querySelector('.tiny');
            if (tiny) {
              tiny.textContent = data.progress.current + '/' + data.progress.target + ' ' + (data.progress.unit || '');
            }
          }
        })
        .catch(function () {});
    }

    if (plusBtn) {
      plusBtn.addEventListener('click', function () {
        submitUnits((parseFloat(currentInput.value) || 0) + 1);
      });
    }
    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        submitUnits(parseFloat(currentInput.value) || 0);
      });
    }
  }

  function initGoalBinaryToggle() {
    var btn = document.getElementById('editBinaryToggle');
    var form = document.getElementById('goalBinaryToggleForm');
    var modeSelect = document.getElementById('editProgressMode');
    if (modeSelect) {
      modeSelect.addEventListener('change', function () {
        var toggle = document.getElementById('editBinaryToggle');
        var done = toggle ? toggle.getAttribute('data-done') === '1' : false;
        syncGoalProgressUi(modeSelect.value, done);
      });
    }
    if (!btn || !form) {
      return;
    }
    btn.addEventListener('click', function () {
      var grid = document.getElementById('editMonthGrid');
      var goalId = grid ? grid.getAttribute('data-goal-id') : '';
      if (!goalId) {
        return;
      }
      var csrf = form.querySelector('input[name="_csrf"]');
      var body = new FormData();
      body.append('_csrf', csrf ? csrf.value : '');
      body.append('r', '/goals/' + goalId + '/binary');
      fetch(form.getAttribute('action') || window.location.pathname, {
        method: 'POST',
        body: body,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (!data || !data.ok || !data.progress) {
            return;
          }
          syncGoalProgressUi('binary', !!data.progress.done);
          var row = document.querySelector('[data-edit-goal][data-id="' + goalId + '"]');
          if (row) {
            row.setAttribute('data-progress', String(Math.round(data.progress.percent || 0)));
            row.setAttribute('data-progress-mode', 'binary');
            row.setAttribute('data-status', data.progress.status || '');
          }
          var statusEl = document.getElementById('editStatus');
          if (statusEl && data.progress.status) {
            statusEl.value = data.progress.status;
          }
        })
        .catch(function () {});
    });
  }

  function initHorizonEditModal() {
    var binaryBlock = document.getElementById('horizonBinaryBlock');
    var horizonLabel = document.getElementById('horizonProgressLabel');
    var toggleBtn = document.getElementById('horizonBinaryToggle');

    function applyHorizonDone(done) {
      if (horizonLabel) {
        horizonLabel.textContent = done ? 'Logrado' : 'En camino';
      }
      if (toggleBtn) {
        toggleBtn.setAttribute('data-done', done ? '1' : '0');
        toggleBtn.textContent = done ? 'Desmarcar' : 'Marcar como logrado';
        toggleBtn.classList.toggle('btn-primary', !done);
        toggleBtn.classList.toggle('btn-ghost', done);
      }
    }

    function fillHorizonEditModal(source) {
      var dialog = document.getElementById('horizonEditModal');
      var route = document.getElementById('horizonEditRoute');
      var deleteRoute = document.getElementById('horizonDeleteRoute');
      var binaryRoute = document.getElementById('horizonBinaryRoute');
      if (!dialog || !source) {
        return null;
      }

      var id = source.getAttribute('data-id') || '';
      var form = document.getElementById('horizonEditForm');
      if (route) {
        route.value = '/horizon/' + id;
      }
      if (form) {
        setFormRoute(form, '/horizon/' + id);
      }
      if (deleteRoute) {
        deleteRoute.value = '/horizon/' + id + '/delete';
      }
      var deleteForm = document.getElementById('horizonDeleteForm');
      if (deleteForm) {
        setFormRoute(deleteForm, '/horizon/' + id + '/delete');
      }
      if (binaryRoute) {
        binaryRoute.value = '/goals/' + id + '/binary';
      }
      var binaryForm = document.getElementById('horizonBinaryToggleForm');
      if (binaryForm) {
        setFormRoute(binaryForm, '/goals/' + id + '/binary');
      }
      if (binaryBlock) {
        binaryBlock.setAttribute('data-goal-id', id);
      }

      var map = {
        horizonEditTitle: 'data-title',
        horizonEditDescription: 'data-description',
        horizonEditAreaId: 'data-area-id',
        horizonEditStatus: 'data-status',
        horizonEditNextAction: 'data-next-action',
      };

      Object.keys(map).forEach(function (fieldId) {
        var el = document.getElementById(fieldId);
        if (!el) {
          return;
        }
        var value = source.getAttribute(map[fieldId]);
        el.value = value == null ? '' : value;
      });

      applyHorizonDone(source.getAttribute('data-done') === '1');

      return dialog;
    }

    document.addEventListener('click', function (event) {
      var row = event.target.closest('[data-edit-horizon]');
      if (!row) {
        return;
      }
      if (event.target.closest('a, button, label, input, select, textarea, form')) {
        return;
      }
      var dialog = fillHorizonEditModal(row);
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      var row = event.target.closest('[data-edit-horizon]');
      if (!row || event.target !== row) {
        return;
      }
      event.preventDefault();
      var dialog = fillHorizonEditModal(row);
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
      }
    });

    var auto = document.getElementById('horizonEditModal');
    if (auto && auto.getAttribute('data-auto-open') === '1') {
      fillHorizonEditModal(auto);
      if (typeof auto.showModal === 'function') {
        auto.showModal();
      }
    }

    var binaryForm = document.getElementById('horizonBinaryToggleForm');
    if (binaryBlock && binaryForm && toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        var goalId = binaryBlock.getAttribute('data-goal-id') || '';
        if (!goalId) {
          return;
        }

        var csrf = binaryForm.querySelector('input[name="_csrf"]');
        var body = new FormData();
        body.append('_csrf', csrf ? csrf.value : '');
        body.append('r', '/goals/' + goalId + '/binary');

        fetch(binaryForm.getAttribute('action') || window.location.pathname, {
          method: 'POST',
          body: body,
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        })
          .then(function (res) {
            return res.json();
          })
          .then(function (data) {
            if (!data || !data.ok || !data.progress) {
              return;
            }
            var done = !!data.progress.done;
            applyHorizonDone(done);

            var statusEl = document.getElementById('horizonEditStatus');
            if (statusEl && data.progress.status) {
              statusEl.value = data.progress.status;
            }

            var row = document.querySelector('[data-edit-horizon][data-id="' + goalId + '"]');
            if (row) {
              row.setAttribute('data-done', done ? '1' : '0');
              row.setAttribute('data-status', data.progress.status || '');
              var flag = row.querySelector('[data-horizon-flag]');
              if (flag) {
                flag.textContent = done ? 'Logrado' : 'En camino';
                flag.classList.toggle('status-completed', done);
                flag.classList.toggle('status-planned', !done);
              }
            }
          })
          .catch(function () {});
      });
    }
  }

  function initHabitEditModal() {
    function fillHabitEditModal(source) {
      var dialog = document.getElementById('habitEditModal');
      var form = document.getElementById('habitEditForm');
      if (!dialog || !form || !source) {
        return null;
      }
      var id = source.getAttribute('data-id') || '';
      setFormRoute(form, '/habits/' + id);
      var archiveForm = document.getElementById('habitArchiveForm');
      var deleteForm = document.getElementById('habitDeleteForm');
      var monthForm = document.getElementById('habitMonthToggleForm');
      var unitsForm = document.getElementById('habitUnitsForm');
      if (archiveForm) {
        setFormRoute(archiveForm, '/habits/' + id + '/archive');
      }
      if (deleteForm) {
        setFormRoute(deleteForm, '/habits/' + id + '/delete');
      }
      if (monthForm) {
        setFormRoute(monthForm, '/habits/' + id + '/months');
      }
      if (unitsForm) {
        setFormRoute(unitsForm, '/habits/' + id + '/units');
      }

      var map = {
        habitEditName: 'data-name',
        habitEditDescription: 'data-description',
        habitEditAreaId: 'data-area-id',
        habitEditTrackingMode: 'data-tracking-mode',
        habitEditTarget: 'data-target',
        habitEditUnit: 'data-unit',
        habitEditFrequency: 'data-frequency',
        habitEditCurrent: 'data-current',
        habitEditTargetLive: 'data-target',
      };
      Object.keys(map).forEach(function (fieldId) {
        var el = document.getElementById(fieldId);
        if (!el) {
          return;
        }
        var value = source.getAttribute(map[fieldId]);
        el.value = value == null ? '' : value;
      });

      var grid = document.getElementById('habitEditMonthGrid');
      if (grid) {
        grid.setAttribute('data-habit-id', id);
      }
      var checked = {};
      (source.getAttribute('data-months') || '').split(',').forEach(function (part) {
        var n = parseInt(part, 10);
        if (n >= 1 && n <= 12) {
          checked[n] = true;
        }
      });
      applyMonthChecks(checked, grid, document.getElementById('habitEditProgressLabel'));
      syncHabitEditMode(source.getAttribute('data-tracking-mode') || 'months');
      updateHabitUnitsLabel();
      return dialog;
    }

    function syncHabitEditMode(mode) {
      mode = mode === 'units' || mode === 'daily' ? mode : 'months';
      var unitsFields = document.getElementById('habitEditUnitsFields');
      var freqField = document.getElementById('habitEditFrequencyField');
      var monthsBlock = document.getElementById('habitEditMonthsBlock');
      var unitsBlock = document.getElementById('habitEditUnitsBlock');
      if (unitsFields) {
        unitsFields.hidden = mode !== 'units';
        setBlockFieldsEnabled(unitsFields, mode === 'units');
      }
      if (freqField) {
        freqField.hidden = mode !== 'daily';
        setBlockFieldsEnabled(freqField, mode === 'daily');
      }
      if (monthsBlock) {
        monthsBlock.hidden = mode !== 'months';
      }
      if (unitsBlock) {
        unitsBlock.hidden = mode !== 'units';
      }
    }

    function updateHabitUnitsLabel() {
      var label = document.getElementById('habitEditUnitsLabel');
      var current = document.getElementById('habitEditCurrent');
      var target = document.getElementById('habitEditTargetLive');
      if (!label || !current || !target) {
        return;
      }
      var c = parseFloat(current.value) || 0;
      var t = parseFloat(target.value) || 0;
      var pct = t > 0 ? Math.min(100, Math.round((c / t) * 100)) : 0;
      label.textContent = pct + '% · ' + c + '/' + t;
    }

    document.addEventListener('click', function (event) {
      var row = event.target.closest('[data-edit-habit]');
      if (!row) {
        return;
      }
      if (event.target.closest('form, a, button, label, input')) {
        return;
      }
      var dialog = fillHabitEditModal(row);
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      var row = event.target.closest('[data-edit-habit]');
      if (!row || event.target !== row) {
        return;
      }
      event.preventDefault();
      var dialog = fillHabitEditModal(row);
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
      }
    });

    var auto = document.getElementById('habitEditModal');
    if (auto && auto.getAttribute('data-auto-open') === '1') {
      fillHabitEditModal(auto);
      if (typeof auto.showModal === 'function') {
        auto.showModal();
      }
    }

    var modeSelect = document.getElementById('habitEditTrackingMode');
    if (modeSelect) {
      modeSelect.addEventListener('change', function () {
        syncHabitEditMode(modeSelect.value);
      });
    }

    var grid = document.getElementById('habitEditMonthGrid');
    var monthForm = document.getElementById('habitMonthToggleForm');
    if (grid && monthForm) {
      grid.querySelectorAll('input[data-month]').forEach(function (input) {
        input.addEventListener('change', function () {
          var habitId = grid.getAttribute('data-habit-id') || '';
          var month = input.getAttribute('data-month') || '';
          if (!habitId || !month) {
            return;
          }
          setFormRoute(monthForm, '/habits/' + habitId + '/months');
          var body = new FormData(monthForm);
          body.set('month', month);
          fetch(appIndex(), {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          })
            .then(function (res) {
              return res.json();
            })
            .then(function (data) {
              if (!data || !data.ok || !data.progress) {
                input.checked = !input.checked;
                return;
              }
              applyMonthChecks(data.progress.months || {}, grid, document.getElementById('habitEditProgressLabel'));
              var row = document.querySelector('[data-edit-habit][data-id="' + habitId + '"]');
              if (row) {
                var months = data.progress.months || {};
                var csv = [];
                Object.keys(months).forEach(function (k) {
                  if (months[k]) {
                    csv.push(k);
                  }
                });
                row.setAttribute('data-months', csv.join(','));
                row.setAttribute('data-progress', String(Math.round(data.progress.percent || 0)));
              }
            })
            .catch(function () {
              input.checked = !input.checked;
            });
        });
      });
    }

    var plusBtn = document.getElementById('habitEditUnitsPlus');
    var saveBtn = document.getElementById('habitEditUnitsSave');
    var currentInput = document.getElementById('habitEditCurrent');
    var targetLive = document.getElementById('habitEditTargetLive');
    if (currentInput) {
      currentInput.addEventListener('input', updateHabitUnitsLabel);
    }
    if (targetLive) {
      targetLive.addEventListener('input', updateHabitUnitsLabel);
    }
    if (plusBtn && currentInput) {
      plusBtn.addEventListener('click', function () {
        currentInput.value = String((parseFloat(currentInput.value) || 0) + 1);
        updateHabitUnitsLabel();
      });
    }
    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var gridEl = document.getElementById('habitEditMonthGrid');
        var habitId = gridEl ? gridEl.getAttribute('data-habit-id') : '';
        var unitsForm = document.getElementById('habitUnitsForm');
        if (!habitId || !unitsForm || !currentInput) {
          return;
        }
        setFormRoute(unitsForm, '/habits/' + habitId + '/units');
        var body = new FormData(unitsForm);
        body.set('current_value', currentInput.value || '0');
        if (targetLive) {
          body.set('target_per_period', targetLive.value || '12');
        }
        fetch(appIndex(), {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(function (res) {
            return res.json();
          })
            .then(function (data) {
            if (!data || !data.ok) {
              showToast((data && data.error) || 'No se pudo guardar.', true);
              return;
            }
            if (data.progress) {
              var cur = data.progress.current_value != null ? data.progress.current_value : data.progress.current;
              var tgt = data.progress.target != null ? data.progress.target : data.progress.target_per_period;
              currentInput.value = cur;
              if (targetLive && tgt != null) {
                targetLive.value = tgt;
              }
              updateHabitUnitsLabel();
              var row = document.querySelector('[data-edit-habit][data-id="' + habitId + '"]');
              if (row) {
                row.setAttribute('data-current', String(cur));
                if (tgt != null) {
                  row.setAttribute('data-target', String(tgt));
                }
                row.setAttribute('data-progress', String(Math.round(data.progress.percent || 0)));
              }
            }
            showToast('Unidades actualizadas.', false);
          })
          .catch(function () {
            showToast('Error de red al guardar.', true);
          });
      });
    }
  }

  function initHabitTrackingFields() {
    var mode = document.getElementById('habitTrackingMode');
    var unitsFields = document.getElementById('habitUnitsFields');
    if (!mode || !unitsFields) {
      return;
    }
    var sync = function () {
      unitsFields.hidden = mode.value !== 'units';
    };
    mode.addEventListener('change', sync);
    sync();
  }

  function initBookModal() {
    var dialog = document.getElementById('bookModal');
    var form = document.getElementById('bookForm');
    var route = document.getElementById('bookRoute');
    var titleEl = document.getElementById('bookModalTitle');
    var submitEl = document.getElementById('bookSubmit');
    if (!dialog || !form || !route) {
      return;
    }

    var defaultYear = dialog.getAttribute('data-default-year') || String(new Date().getFullYear());

    function setField(id, value) {
      var el = document.getElementById(id);
      if (el) {
        el.value = value == null ? '' : String(value);
      }
    }

    function resetCreate() {
      route.value = '/books';
      setFormRoute(form, '/books');
      if (titleEl) {
        titleEl.textContent = 'Nuevo libro';
      }
      if (submitEl) {
        submitEl.textContent = 'Guardar';
      }
      form.reset();
      route.value = '/books';
      setField('bookYear', defaultYear);
      setField('bookPagesRead', '0');
      setField('bookStatus', 'planned');
    }

    function fillEdit(source) {
      var id = source.getAttribute('data-id') || '';
      route.value = '/books/' + id;
      setFormRoute(form, '/books/' + id);
      if (titleEl) {
        titleEl.textContent = 'Editar libro';
      }
      if (submitEl) {
        submitEl.textContent = 'Guardar cambios';
      }
      setField('bookTitle', source.getAttribute('data-title'));
      setField('bookYear', source.getAttribute('data-year') || defaultYear);
      setField('bookPlannedMonth', source.getAttribute('data-planned-month') || '');
      setField('bookStatus', source.getAttribute('data-status') || 'planned');
      setField('bookFinishedAt', source.getAttribute('data-finished-at') || '');
      setField('bookPages', source.getAttribute('data-pages') || '');
      setField('bookPagesRead', source.getAttribute('data-pages-read') || '0');
      setField('bookGoodreads', source.getAttribute('data-goodreads') || '');
      setField('bookNotes', source.getAttribute('data-notes') || '');
    }

    document.querySelectorAll('[data-open-modal="bookModal"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        resetCreate();
      });
    });

    document.querySelectorAll('[data-edit-book]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        fillEdit(btn);
        if (typeof dialog.showModal === 'function') {
          dialog.showModal();
        }
      });
    });
  }

  function showToast(message, isError) {
    var existing = document.querySelector('.lq-toast');
    if (existing) {
      existing.remove();
    }
    var el = document.createElement('div');
    el.className = 'flash ' + (isError ? 'flash-error' : 'flash-success') + ' lq-toast';
    el.setAttribute('role', 'status');
    el.textContent = message;
    document.body.appendChild(el);
    window.setTimeout(function () {
      if (el.parentNode) {
        el.remove();
      }
    }, 4500);
  }

  /**
   * Guardado fiable de modales / data-lq-save:
   * POST a index.php con r en el body → JSON → location.replace.
   */
  function initLqSaveForms() {
    document.addEventListener('invalid', function (event) {
      var el = event.target;
      if (!el || !el.form) {
        return;
      }
      if (!el.form.hasAttribute('data-lq-save') && !el.form.closest('dialog.modal')) {
        return;
      }
      event.preventDefault();
      showToast('Revisá los campos: hay un valor inválido (' + (el.name || el.id || 'campo') + ').', true);
      try {
        el.focus();
      } catch (e) {
        /* ignore */
      }
    }, true);

    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!form || form.tagName !== 'FORM') {
        return;
      }
      if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') {
        return;
      }
      if (form.hasAttribute('data-habit-toggle') || form.hasAttribute('data-no-lq-save')) {
        return;
      }
      var formId = form.id || '';
      if (/ToggleForm$|UnitsForm$/.test(formId)) {
        return;
      }

      var shouldHandle = form.hasAttribute('data-lq-save') || !!form.closest('dialog.modal');
      if (!shouldHandle) {
        return;
      }

      var rInput = form.querySelector('input[name="r"]');
      if (!rInput || !String(rInput.value || '').trim()) {
        showToast('No se pudo guardar: falta la ruta del formulario.', true);
        event.preventDefault();
        return;
      }

      if (String(rInput.value).indexOf('/goals/0') === 0 && form.id === 'goalEditForm') {
        showToast('No se pudo guardar: objetivo sin ID. Cerrá el modal y abrilo de nuevo.', true);
        event.preventDefault();
        return;
      }

      event.preventDefault();
      form.action = appIndex();

      var btn = event.submitter;
      var prevLabel = null;
      if (btn && btn.tagName === 'BUTTON') {
        prevLabel = btn.textContent;
        btn.textContent = /eliminar|archivar/i.test(prevLabel || '') ? '…' : 'Guardando…';
      }

      var body = new FormData(form);
      if (btn && btn.name) {
        body.append(btn.name, btn.value || '');
      }

      fetch(appIndex(), {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then(function (res) {
          var ct = res.headers.get('content-type') || '';
          if (ct.indexOf('application/json') !== -1) {
            return res.json().then(function (data) {
              return { kind: 'json', ok: res.ok, data: data, url: res.url };
            });
          }
          return { kind: 'html', ok: res.ok, url: res.url, status: res.status };
        })
        .then(function (result) {
          var dialog = form.closest('dialog');
          if (result.kind === 'json' && result.data) {
            if (result.data.ok === false || result.data.error) {
              if (btn && prevLabel !== null) {
                btn.textContent = prevLabel;
              }
              showToast(result.data.error || 'No se pudo guardar.', true);
              return;
            }
            if (result.data.redirect) {
              if (dialog && dialog.open) {
                try {
                  dialog.close();
                } catch (e) {
                  /* ignore */
                }
              }
              if (result.data.message) {
                showToast(result.data.message, false);
              }
              if (typeof window.__lqSaveScroll === 'function') {
                var focusId = null;
                var focusKind = null;
                var rv = rInput ? String(rInput.value || '') : '';
                var hm = rv.match(/^\/(?:horizon|goals|habits)\/(\d+)/);
                if (hm) {
                  focusId = hm[1];
                  focusKind = rv.indexOf('/horizon/') === 0 ? 'horizon' : (rv.indexOf('/habits/') === 0 ? 'habit' : 'goal');
                }
                // Guardar path del destino del redirect para que coincida al recargar.
                var y = window.scrollY || document.documentElement.scrollTop || 0;
                var destPath = pageKeyFromUrl(result.data.redirect);
                try {
                  sessionStorage.setItem('lq:scroll', JSON.stringify({
                    path: destPath || (location.pathname + location.search),
                    y: y,
                    t: Date.now(),
                    focusId: focusId,
                    focusKind: focusKind,
                  }));
                } catch (e) {
                  window.__lqSaveScroll(focusId ? { focusId: focusId, focusKind: focusKind } : {});
                }
              }
              window.location.replace(result.data.redirect);
              return;
            }
          }
          if (result.kind === 'html' && result.url && /r=%2Flogin|r=\/login|[?&]r=\/login/.test(result.url)) {
            if (btn && prevLabel !== null) {
              btn.textContent = prevLabel;
            }
            showToast('Sesión expirada. Volvé a iniciar sesión.', true);
            window.location.replace(result.url);
            return;
          }
          if (result.url && result.ok) {
            window.location.replace(result.url);
            return;
          }
          if (btn && prevLabel !== null) {
            btn.textContent = prevLabel;
          }
          showToast('No se pudo guardar (respuesta inesperada).', true);
        })
        .catch(function () {
          if (btn && prevLabel !== null) {
            btn.textContent = prevLabel;
          }
          showToast('Error de red al guardar. Probá de nuevo.', true);
        });
    });
  }

  function initSortableTables() {
    document.querySelectorAll('[data-sortable]').forEach(function (table) {
      var head = table.querySelector('.goal-table-head, .archive-table-head');
      if (!head) {
        return;
      }
      head.querySelectorAll('.sort-btn[data-sort]').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
          event.preventDefault();
          event.stopPropagation();
          var key = btn.getAttribute('data-sort') || '';
          if (!key) {
            return;
          }
          var type = btn.getAttribute('data-sort-type') || 'text';
          var prev = table.getAttribute('data-sort-key');
          var dir = table.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
          if (prev !== key) {
            dir = type === 'number' || type === 'date' ? 'desc' : 'asc';
          }
          table.setAttribute('data-sort-key', key);
          table.setAttribute('data-sort-dir', dir);

          head.querySelectorAll('.sort-btn').forEach(function (other) {
            other.removeAttribute('aria-sort');
            other.classList.remove('is-asc', 'is-desc');
          });
          btn.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
          btn.classList.add(dir === 'asc' ? 'is-asc' : 'is-desc');

          var rows = Array.prototype.slice.call(table.children).filter(function (el) {
            return el !== head && (el.classList.contains('goal-row') || el.classList.contains('archive-row'));
          });

          rows.sort(function (a, b) {
            var av = a.getAttribute('data-sort-' + key);
            var bv = b.getAttribute('data-sort-' + key);
            if (av == null) {
              av = '';
            }
            if (bv == null) {
              bv = '';
            }
            var cmp = 0;
            if (type === 'number') {
              cmp = (parseFloat(av) || 0) - (parseFloat(bv) || 0);
            } else if (type === 'date') {
              var ad = av || '9999-99-99';
              var bd = bv || '9999-99-99';
              cmp = ad < bd ? -1 : ad > bd ? 1 : 0;
            } else {
              cmp = String(av).localeCompare(String(bv), 'es', { sensitivity: 'base', numeric: true });
            }
            return dir === 'asc' ? cmp : -cmp;
          });

          rows.forEach(function (row) {
            table.appendChild(row);
          });
        });
      });
    });
  }

  function initModals() {
    document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-open-modal');
        var dialog = id ? document.getElementById(id) : null;
        if (dialog && typeof dialog.showModal === 'function') {
          dialog.showModal();
        }
      });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var dialog = btn.closest('dialog');
        if (dialog) {
          dialog.close();
        }
      });
    });
  }

  function initScrollTop() {
    const btn = document.getElementById('scrollTopBtn');
    if (!btn) {
      return;
    }
    const sync = function () {
      const show = window.scrollY > 240;
      btn.hidden = !show;
      btn.classList.toggle('is-visible', show);
    };
    window.addEventListener('scroll', sync, { passive: true });
    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    sync();
  }

  /**
   * Guarda la posición de scroll antes de un submit y la restaura al volver
   * a la misma vista, para no perder el lugar al editar un objetivo, hábito u horizonte.
   */
  function initScrollMemory() {
    const KEY = 'lq:scroll';
    const MAX_AGE = 30000;

    const pageKey = function (href) {
      return pageKeyFromUrl(href);
    };

    const read = function () {
      try {
        return JSON.parse(sessionStorage.getItem(KEY) || 'null');
      } catch (e) {
        return null;
      }
    };

    const write = function (extra) {
      const y = window.scrollY || document.documentElement.scrollTop || 0;
      const payload = Object.assign({ path: pageKey(), y: y, t: Date.now() }, extra || {});
      try {
        sessionStorage.setItem(KEY, JSON.stringify(payload));
      } catch (e) {
        /* sessionStorage bloqueado */
      }
      return payload;
    };

    window.__lqSaveScroll = write;

    document.addEventListener('submit', function (event) {
      const form = event.target;
      if (!form || form.hasAttribute('data-reset-scroll')) {
        return;
      }
      const y = window.scrollY || document.documentElement.scrollTop || 0;
      if (y < 40 && !form.closest('dialog.modal')) {
        return;
      }
      var focusId = null;
      var rInput = form.querySelector('input[name="r"]');
      var rVal = rInput ? String(rInput.value || '') : '';
      var m = rVal.match(/^\/(?:horizon|goals|habits)\/(\d+)/);
      if (m) {
        focusId = m[1];
      }
      write(focusId ? { focusId: focusId, focusKind: rVal.indexOf('/horizon/') === 0 ? 'horizon' : (rVal.indexOf('/habits/') === 0 ? 'habit' : 'goal') } : {});
    }, true);

    const saved = read();
    try {
      sessionStorage.removeItem(KEY);
    } catch (e) {
      /* ignorado */
    }
    if (!saved || saved.path !== pageKey() || Date.now() - saved.t > MAX_AGE) {
      return;
    }

    const restore = function () {
      if (saved.focusId) {
        var selector =
          saved.focusKind === 'horizon'
            ? '[data-edit-horizon][data-id="' + saved.focusId + '"]'
            : saved.focusKind === 'habit'
              ? '[data-habit-id="' + saved.focusId + '"], [data-id="' + saved.focusId + '"]'
              : '[data-edit-goal][data-id="' + saved.focusId + '"]';
        var el = document.querySelector(selector);
        if (el) {
          el.scrollIntoView({ block: 'center' });
          el.classList.add('is-scroll-focus');
          window.setTimeout(function () {
            el.classList.remove('is-scroll-focus');
          }, 1600);
          return;
        }
      }
      window.scrollTo(0, saved.y || 0);
    };
    requestAnimationFrame(restore);
    window.addEventListener('load', restore, { once: true });
    window.setTimeout(restore, 120);
  }

  document.addEventListener('DOMContentLoaded', function () {
    initScrollMemory();
    initTheme();
    initThemeToggle();
    initHabitToggles();
    initTaskToggles();
    initWeeklyTaskModal();
    initCharts();
    initSidebar();
    initSortableTables();
    initModals();
    initLqSaveForms();
    initBookModal();
    initGoalEditModal();
    initHorizonEditModal();
    initHabitEditModal();
    initHabitTrackingFields();
    initScrollTop();
    void csrfToken;
  });
})();
