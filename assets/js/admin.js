/* ==========================================================
   admin.js: admin panel logic (talks to admin/actions.php)
   ========================================================== */
(() => {
	'use strict';

	const $ = (selector, root = document) => root.querySelector(selector);
	const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
	const esc = value =>
		String(value ?? '').replace(
			/[&<>"']/g,
			c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
		);

	const csrfToken = $('meta[name="csrf-token"]').content;
	const toast = (icon, title) =>
		Swal.fire({
			toast: true,
			position: 'top-end',
			icon,
			title,
			showConfirmButton: false,
			timer: 2500,
		});

	/* ---------- API helper ---------- */
	async function api(action, { method = 'GET', params = {}, body = null } = {}) {
		const url = 'actions.php?' + new URLSearchParams({ action, ...params });
		const options = {
			method,
			headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
		};
		if (body) {
			options.body =
				body instanceof FormData
					? body
					: Object.entries(body).reduce(
							(fd, [k, v]) => (fd.append(k, v), fd),
							new FormData()
						);
		}
		const response = await fetch(url, options);
		if (response.status === 401) {
			location.href = 'login.php';
			throw new Error('Session expired');
		}
		const data = await response.json().catch(() => null);
		if (!data || !data.success) throw new Error((data && data.message) || 'Request failed.');
		return data;
	}
	const fail = error =>
		Swal.fire({
			icon: 'error',
			title: 'Error',
			text: error.message,
			confirmButtonColor: '#012059',
		});

	/* ---------- Constants ---------- */
	const titles = ['Dr.', 'Prof.', 'Mr.', 'Ms.', 'Other'];
	const regCategories = [
		'Delegate',
		'Speaker',
		'Student',
		'Researcher',
		'Academic',
		'Industry Professional',
		'Business',
		'Other',
	];
	const regTypes = ['In-person', 'Virtual'];
	const tracks = [
		'Life Sciences',
		'Physical Sciences',
		'Engineering and Technology',
		'Medical and Health Sciences',
		'Environmental Sciences',
		'Computer Science and AI',
		'Other',
	];
	const presentations = ['Oral Presentation', 'Poster Presentation', 'Virtual Presentation'];
	const statusClass = {
		Pending: 'text-bg-warning',
		Confirmed: 'text-bg-success',
		Accepted: 'text-bg-success',
		Rejected: 'text-bg-danger',
		'Under Review': 'text-bg-info',
		'Revision Required': 'text-bg-secondary',
	};
	const badge = status =>
		`<span class="badge rounded-pill ${statusClass[status] || 'text-bg-light'}">${esc(status)}</span>`;
	const fullName = r => `${r.title || ''} ${r.first_name} ${r.last_name}`.trim();
	const day = value => String(value || '').slice(0, 10);

	/* ==========================================================
     Sidebar and views
     ========================================================== */
	const body = document.body;
	const viewTitles = {
		dashboard: 'Dashboard',
		registrations: 'Registrations',
		abstracts: 'Abstract Submissions',
		settings: 'Event Settings',
		password: 'Change Password',
	};

	$('#sidebarToggle').addEventListener('click', () => {
		if (window.innerWidth >= 992) body.classList.toggle('adminCollapsed');
		else body.classList.toggle('sidebarOpen');
	});
	$('#sidebarBackdrop').addEventListener('click', () => body.classList.remove('sidebarOpen'));

	function showView(name) {
		if (!viewTitles[name]) name = 'dashboard';
		$$('.adminView').forEach(el => el.classList.toggle('d-none', el.id !== `view-${name}`));
		$$('.sidebarLink[data-view]').forEach(link =>
			link.classList.toggle('active', link.dataset.view === name)
		);
		$('#breadcrumbCurrent').textContent = viewTitles[name];
		body.classList.remove('sidebarOpen');
		window.scrollTo({ top: 0 });
	}

	$$('[data-view], [data-view-link]').forEach(link => {
		link.addEventListener('click', event => {
			event.preventDefault();
			const name = link.dataset.view || link.dataset.viewLink;
			history.replaceState(null, '', `#${name}`);
			showView(name);
		});
	});

	/* ==========================================================
     Dashboard
     ========================================================== */
	const charts = {};
	const palette = ['#012059', '#2563EB', '#FFC928', '#10B981', '#F97316', '#8B5CF6', '#94A3B8'];

	function drawChart(id, config) {
		if (charts[id]) charts[id].destroy();
		charts[id] = new Chart($(`#${id}`), config);
	}

	async function loadStats() {
		const data = await api('stats');
		const r = data.registrations;
		const a = data.abstracts;

		const cards = [
			['Total Registrations', r.total, 'fa-users', 'primary'],
			['Total Abstracts', a.total, 'fa-file-lines', 'secondary'],
			['Pending Registrations', r.pending, 'fa-hourglass-half', 'warning'],
			['Confirmed Registrations', r.confirmed, 'fa-circle-check', 'success'],
			['Pending Abstracts', a.pending, 'fa-clock', 'warning'],
			['Accepted Abstracts', a.accepted, 'fa-check-double', 'success'],
			['Rejected Abstracts', a.rejected, 'fa-circle-xmark', 'danger'],
		];
		$('#statCards').innerHTML = cards
			.map(
				([label, value, icon, color]) => `
      <div class="col-6 col-md-4 col-xl-3">
        <div class="card statCard border-0 shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
          <span class="statIcon bg-${color}-subtle text-${color}-emphasis"><i class="fa-solid ${icon}" aria-hidden="true"></i></span>
          <div><div class="statValue">${value}</div><div class="small text-body-secondary">${label}</div></div>
        </div></div>
      </div>`
			)
			.join('');

		$('#recentRegs').innerHTML = data.recentRegs.length
			? data.recentRegs
					.map(
						x =>
							`<tr><td>${esc(x.registration_id)}</td><td>${esc(fullName(x))}</td><td>${badge(x.status)}</td></tr>`
					)
					.join('')
			: '<tr><td colspan="3" class="text-body-secondary">No registrations yet.</td></tr>';
		$('#recentAbs').innerHTML = data.recentAbstracts.length
			? data.recentAbstracts
					.map(
						x =>
							`<tr><td>${esc(x.abstract_id)}</td><td class="wrapCell">${esc(x.abstract_title)}</td><td>${badge(x.status)}</td></tr>`
					)
					.join('')
			: '<tr><td colspan="3" class="text-body-secondary">No abstracts yet.</td></tr>';

		const base = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: { legend: { display: false } },
		};
		const intAxis = { beginAtZero: true, ticks: { precision: 0 } };

		drawChart('chartRegTrend', {
			type: 'line',
			data: {
				labels: data.regTrend.labels,
				datasets: [
					{
						data: data.regTrend.values,
						borderColor: '#2563EB',
						backgroundColor: 'rgba(37,99,235,.12)',
						fill: true,
						tension: 0.35,
					},
				],
			},
			options: { ...base, scales: { y: intAxis } },
		});
		drawChart('chartAbsTrend', {
			type: 'bar',
			data: {
				labels: data.absTrend.labels,
				datasets: [
					{ data: data.absTrend.values, backgroundColor: '#FFC928', borderRadius: 4 },
				],
			},
			options: { ...base, scales: { y: intAxis } },
		});
		drawChart('chartCountry', {
			type: 'doughnut',
			data: {
				labels: data.countries.map(c => c.label),
				datasets: [{ data: data.countries.map(c => c.total), backgroundColor: palette }],
			},
			options: { ...base, plugins: { legend: { display: true, position: 'right' } } },
		});
		drawChart('chartCategory', {
			type: 'bar',
			data: {
				labels: data.categories.map(c => c.label),
				datasets: [
					{
						data: data.categories.map(c => c.total),
						backgroundColor: '#012059',
						borderRadius: 4,
					},
				],
			},
			options: { ...base, indexAxis: 'y', scales: { x: intAxis } },
		});
	}

	/* ---------- Notification bell + AJAX polling for new submissions ---------- */
	let lastCounts = null;

	function renderBell(c) {
		$('#notifyRegs').textContent = `${c.pendingRegs} pending registrations`;
		$('#notifyAbs').textContent = `${c.pendingAbs} abstracts awaiting review`;
		const total = c.pendingRegs + c.pendingAbs;
		const bell = $('#notifyCount');
		bell.textContent = total;
		bell.classList.toggle('d-none', total === 0);
	}

	async function pollCounts() {
		if (document.hidden) return;
		try {
			const counts = await api('counts');
			renderBell(counts);
			if (
				lastCounts &&
				(counts.lastReg > lastCounts.lastReg || counts.lastAbs > lastCounts.lastAbs)
			) {
				toast('info', 'New submission received');
				refreshAll();
			}
			lastCounts = counts;
		} catch (error) {
			/* ignore transient network errors while polling */
		}
	}

	/* ==========================================================
     Registrations and abstracts lists (shared logic)
     ========================================================== */
	const entities = {
		registrations: {
			statuses: ['Pending', 'Confirmed', 'Rejected'],
			categories: regCategories,
			quick: [
				['Confirmed', 'fa-check', 'success', 'Confirm'],
				['Rejected', 'fa-xmark', 'danger', 'Reject'],
			],
			idKey: 'registration_id',
			cells: r => [
				`<span class="fw-semibold">${esc(r.registration_id)}</span>`,
				esc(fullName(r)),
				esc(r.email),
				esc(r.phone),
				esc(r.country),
				esc(r.organization),
				esc(r.registration_category),
				esc(r.participation_type),
				esc(day(r.created_at)),
				badge(r.status),
			],
		},
		abstracts: {
			statuses: ['Pending', 'Under Review', 'Accepted', 'Rejected', 'Revision Required'],
			categories: tracks,
			quick: [
				['Accepted', 'fa-check', 'success', 'Accept'],
				['Rejected', 'fa-xmark', 'danger', 'Reject'],
			],
			idKey: 'abstract_id',
			download: true,
			cells: r => [
				`<span class="fw-semibold">${esc(r.abstract_id)}</span>`,
				esc(fullName(r)),
				esc(r.email),
				`<span class="wrapCell d-inline-block">${esc(r.abstract_title)}</span>`,
				esc(r.research_track),
				esc(r.presentation_type),
				esc(r.country),
				esc(day(r.created_at)),
				badge(r.status),
			],
		},
	};

	const lists = {};

	function createList(section) {
		const key = section.dataset.entity;
		const cfg = entities[key];
		const role = name => $(`[data-role="${name}"]`, section);
		const state = {
			page: 1,
			sort: 'created_at',
			dir: 'desc',
			selected: new Set(),
			rows: [],
			pages: 1,
			total: 0,
			countriesKey: '',
		};

		role('category').innerHTML += cfg.categories
			.map(c => `<option>${esc(c)}</option>`)
			.join('');

		const filterParams = () => ({
			search: role('search').value.trim(),
			status: role('status').value,
			category: role('category').value,
			country: role('country').value,
			from: role('from').value,
			to: role('to').value,
		});

		function renderRows() {
			const start = (state.page - 1) * 10;
			role('body').innerHTML = state.rows.length
				? state.rows
						.map((row, i) => {
							const quick = cfg.quick
								.map(
									([status, icon, color, label]) =>
										`<button type="button" class="btn btn-sm btn-outline-${color} actionBtn" data-act="status" data-status="${status}" data-id="${row.id}" aria-label="${label} ${esc(row[cfg.idKey])}" title="${label}"><i class="fa-solid ${icon}"></i></button>`
								)
								.join(' ');
							const download = cfg.download
								? `<button type="button" class="btn btn-sm btn-outline-secondary actionBtn" data-act="download" data-id="${row.id}" aria-label="Download file" title="Download file"><i class="fa-solid fa-download"></i></button>`
								: '';
							return `<tr>
          <td><input type="checkbox" class="form-check-input rowCheck" data-id="${row.id}" ${state.selected.has(row.id) ? 'checked' : ''} aria-label="Select row"></td>
          <td>${start + i + 1}</td>
          ${cfg
				.cells(row)
				.map(cell => `<td>${cell}</td>`)
				.join('')}
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-primary actionBtn" data-act="view" data-id="${row.id}" aria-label="View or edit" title="View / Edit"><i class="fa-solid fa-eye"></i></button>
            ${quick} ${download}
            <button type="button" class="btn btn-sm btn-outline-danger actionBtn" data-act="delete" data-id="${row.id}" aria-label="Delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
          </td></tr>`;
						})
						.join('')
				: '<tr><td colspan="16" class="text-center text-body-secondary py-4">No records found.</td></tr>';

			role('selectall').checked =
				state.rows.length > 0 && state.rows.every(r => state.selected.has(r.id));
			renderBulk();
		}

		function renderBulk() {
			role('bulkbar').classList.toggle('d-none', state.selected.size === 0);
			role('bulkbar').classList.toggle('d-flex', state.selected.size > 0);
			role('bulkcount').textContent = state.selected.size;
		}

		function renderPager() {
			const first = state.total ? (state.page - 1) * 10 + 1 : 0;
			role('info').textContent = state.total
				? `Showing ${first}–${first + state.rows.length - 1} of ${state.total}`
				: '0 results';
			role('pager').innerHTML =
				`<button class="btn btn-outline-secondary btn-sm" data-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}>Prev</button>` +
				`<button class="btn btn-outline-secondary btn-sm disabled">${state.page} / ${state.pages}</button>` +
				`<button class="btn btn-outline-secondary btn-sm" data-page="${state.page + 1}" ${state.page >= state.pages ? 'disabled' : ''}>Next</button>`;
		}

		function renderSort() {
			$$('.sortBtn', section).forEach(btn => {
				const active = btn.dataset.sort === state.sort;
				btn.querySelector('i').className =
					'fa-solid ' +
					(active ? (state.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort');
			});
		}

		function renderCountries(countries) {
			const keyed = countries.join('|');
			if (keyed === state.countriesKey) return;
			state.countriesKey = keyed;
			const current = role('country').value;
			role('country').innerHTML =
				'<option value="">All countries</option>' +
				countries.map(c => `<option>${esc(c)}</option>`).join('');
			role('country').value = countries.includes(current) ? current : '';
		}

		async function load() {
			try {
				const data = await api('list', {
					params: {
						entity: key,
						page: state.page,
						sort: state.sort,
						dir: state.dir,
						per_page: 10,
						...filterParams(),
					},
				});
				Object.assign(state, {
					rows: data.rows,
					page: data.page,
					pages: data.pages,
					total: data.total,
				});
				renderCountries(data.countries);
				renderRows();
				renderPager();
				renderSort();
			} catch (error) {
				fail(error);
			}
		}

		const reload = (resetPage = true) => {
			if (resetPage) state.page = 1;
			state.selected.clear();
			load();
		};
		let searchTimer;
		role('search').addEventListener('input', () => {
			clearTimeout(searchTimer);
			searchTimer = setTimeout(reload, 300);
		});
		['status', 'category', 'country', 'from', 'to'].forEach(name =>
			role(name).addEventListener('change', () => reload())
		);
		role('reset').addEventListener('click', () => {
			['search', 'status', 'category', 'country', 'from', 'to'].forEach(name => {
				role(name).value = '';
			});
			reload();
		});

		role('pager').addEventListener('click', event => {
			const btn = event.target.closest('[data-page]');
			if (btn && !btn.disabled) {
				state.page = Number(btn.dataset.page);
				load();
			}
		});

		$$('.sortBtn', section).forEach(btn =>
			btn.addEventListener('click', () => {
				state.dir = state.sort === btn.dataset.sort && state.dir === 'asc' ? 'desc' : 'asc';
				state.sort = btn.dataset.sort;
				load();
			})
		);

		role('selectall').addEventListener('change', event => {
			state.rows.forEach(r =>
				event.target.checked ? state.selected.add(r.id) : state.selected.delete(r.id)
			);
			renderRows();
		});

		role('body').addEventListener('change', event => {
			const box = event.target.closest('.rowCheck');
			if (!box) return;
			const id = Number(box.dataset.id);
			if (box.checked) state.selected.add(id);
			else state.selected.delete(id);
			role('selectall').checked = state.rows.every(r => state.selected.has(r.id));
			renderBulk();
		});

		async function setStatus(ids, status) {
			try {
				await api('set_status', {
					method: 'POST',
					body: { entity: key, ids: ids.join(','), status },
				});
				toast('success', 'Status updated');
				refreshAll();
			} catch (error) {
				fail(error);
			}
		}

		async function remove(ids) {
			const result = await Swal.fire({
				icon: 'warning',
				title: 'Delete permanently?',
				text: `${ids.length} record(s) will be removed${key === 'abstracts' ? ' together with their files' : ''}.`,
				showCancelButton: true,
				confirmButtonText: 'Delete',
				confirmButtonColor: '#DC3545',
			});
			if (!result.isConfirmed) return;
			try {
				await api('delete', { method: 'POST', body: { entity: key, ids: ids.join(',') } });
				toast('success', 'Deleted');
				refreshAll();
			} catch (error) {
				fail(error);
			}
		}

		role('body').addEventListener('click', event => {
			const btn = event.target.closest('[data-act]');
			if (!btn) return;
			const id = Number(btn.dataset.id);
			if (btn.dataset.act === 'view') openModal(key, id);
			if (btn.dataset.act === 'status') setStatus([id], btn.dataset.status);
			if (btn.dataset.act === 'download')
				location.href = `actions.php?action=download&id=${id}`;
			if (btn.dataset.act === 'delete') remove([id]);
		});

		role('bulkapply').addEventListener('click', () =>
			setStatus([...state.selected], role('bulkstatus').value)
		);
		role('bulkdelete').addEventListener('click', () => remove([...state.selected]));
		role('bulkexport').addEventListener('click', () => {
			location.href = `actions.php?action=export&entity=${key}&ids=${[...state.selected].join(',')}`;
		});
		role('export').addEventListener('click', () => {
			location.href =
				'actions.php?' +
				new URLSearchParams({ action: 'export', entity: key, ...filterParams() });
		});

		return { load: () => load() };
	}

	$$('section[data-entity]').forEach(section => {
		lists[section.dataset.entity] = createList(section);
	});

	function refreshAll() {
		loadStats().catch(fail);
		Object.values(lists).forEach(list => list.load());
		pollCounts();
	}

	/* ==========================================================
     Detail / edit modal
     ========================================================== */
	const detailModal = new bootstrap.Modal($('#detailModal'));

	function field(name, label, value = '', o = {}) {
		const id = `f_${name || label.replace(/\W/g, '')}`;
		const attr = name ? `name="${name}"` : '';
		let input;
		if (o.options) {
			input = `<select class="form-select" id="${id}" ${attr}>${o.options.map(v => `<option ${v === value ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select>`;
		} else if (o.rows) {
			input = `<textarea class="form-control" id="${id}" ${attr} rows="${o.rows}" maxlength="${o.max || 1000}">${esc(value)}</textarea>`;
		} else {
			input = `<input class="form-control" id="${id}" ${attr} value="${esc(value)}" maxlength="${o.max || 150}" ${o.readonly ? 'readonly' : ''}>`;
		}
		return `<div class="${o.col || 'col-md-6'}"><label class="form-label fw-semibold" for="${id}">${label}</label>${input}</div>`;
	}

	async function openModal(key, id) {
		let row;
		try {
			row = (await api('get', { params: { entity: key, id } })).row;
		} catch (error) {
			return fail(error);
		}

		const cfg = entities[key];
		let fields;
		if (key === 'registrations') {
			$('#detailTitle').textContent = `Registration ${row.registration_id}`;
			fields = [
				field('', 'Reference', row.registration_id, { readonly: true }),
				field('', 'Email', row.email, { readonly: true }),
				field('', 'Submitted', row.created_at, { readonly: true }),
				field('status', 'Status', row.status, { options: cfg.statuses }),
				field('title', 'Title', row.title, { options: titles, col: 'col-md-3' }),
				field('first_name', 'First name', row.first_name, { col: 'col-md-4', max: 60 }),
				field('last_name', 'Last name', row.last_name, { col: 'col-md-5', max: 60 }),
				field('phone', 'Phone', row.phone, { max: 30 }),
				field('country', 'Country', row.country, { max: 80 }),
				field('organization', 'Organization', row.organization),
				field('designation', 'Designation', row.designation, { max: 100 }),
				field('registration_category', 'Category', row.registration_category, {
					options: regCategories,
				}),
				field('', 'Registration tier', row.registration_tier, { readonly: true }),
				field('', 'Participants', row.participant_count, { readonly: true }),
				field('participation_type', 'Participation', row.participation_type, {
					options: regTypes,
				}),
				field('dietary_requirements', 'Dietary requirements', row.dietary_requirements),
				field('message', 'Message', row.message, { rows: 3, col: 'col-12' }),
			];
		} else {
			$('#detailTitle').textContent = `Abstract ${row.abstract_id}`;
			fields = [
				field('', 'Reference', row.abstract_id, { readonly: true }),
				field('', 'Submitted', row.created_at, { readonly: true }),
				field('', 'Author', fullName(row), { readonly: true }),
				field('', 'Email', row.email, { readonly: true }),
				field('', 'Organization', row.organization, { readonly: true }),
				field('', 'Country', row.country, { readonly: true }),
				field(
					'',
					'File',
					`${row.original_filename} (${Math.round(row.file_size / 1024)} KB)`,
					{ readonly: true, col: 'col-12' }
				),
				field('abstract_title', 'Abstract title', row.abstract_title, {
					col: 'col-12',
					max: 200,
				}),
				field('research_track', 'Track', row.research_track, { options: tracks }),
				field('presentation_type', 'Presentation type', row.presentation_type, {
					options: presentations,
				}),
				field('keywords', 'Keywords', row.keywords, { max: 200 }),
				field('status', 'Status', row.status, { options: cfg.statuses }),
				field('co_authors', 'Co-authors', row.co_authors, {
					rows: 2,
					col: 'col-12',
					max: 500,
				}),
				field('abstract_description', 'Abstract description', row.abstract_description, {
					rows: 8,
					col: 'col-12',
					max: 20000,
				}),
				field('reviewer_notes', 'Internal reviewer notes', row.reviewer_notes, {
					rows: 3,
					col: 'col-12',
					max: 2000,
				}),
			];
		}

		$('#detailBody').innerHTML =
			`<form id="editForm"><div class="row g-3">${fields.join('')}</div></form>`;
		$('#detailFooter').innerHTML =
			(key === 'abstracts'
				? `<a class="btn btn-outline-secondary me-auto" href="actions.php?action=download&id=${id}"><i class="fa-solid fa-download me-2"></i>Download file</a>`
				: '') +
			`<button type="submit" form="editForm" class="btn btn-warning fw-bold">Save changes</button>
       <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>`;

		$('#editForm').addEventListener('submit', async event => {
			event.preventDefault();
			const data = new FormData(event.target);
			data.append('entity', key);
			data.append('id', id);
			try {
				await api('update', { method: 'POST', body: data });
				detailModal.hide();
				toast('success', 'Saved');
				refreshAll();
			} catch (error) {
				fail(error);
			}
		});
		detailModal.show();
	}

	/* ==========================================================
     Event settings
     ========================================================== */
	const settingsForm = $('#settingsForm');

	function slideHtml(s, index) {
		const id = s.id;
		return `<div class="accordion-item">
      <h3 class="accordion-header"><button class="accordion-button ${index ? 'collapsed' : ''}" type="button" data-bs-toggle="collapse" data-bs-target="#slide${id}" aria-expanded="${index ? 'false' : 'true'}">Slide ${index + 1}</button></h3>
      <div id="slide${id}" class="accordion-collapse collapse ${index ? '' : 'show'}" data-bs-parent="#slideAccordion">
        <div class="accordion-body"><div class="row g-3">
          ${field(`slide_subheading_${id}`, 'Badge text', s.subheading, { max: 200 })}
          ${field(`slide_heading_${id}`, 'Heading', s.heading, { max: 150 })}
          ${field(`slide_description_${id}`, 'Description', s.description, { rows: 2, col: 'col-12', max: 500 })}
          ${field(`slide_primary_${id}`, 'Primary button label', s.primary_button_text, { max: 50 })}
          ${field(`slide_secondary_${id}`, 'Secondary button label', s.secondary_button_text, { max: 50 })}
          <div class="col-12"><label class="form-label fw-semibold" for="slideImg${id}">Background image (JPG, PNG, WebP, max 3 MB)</label>
            <input type="file" id="slideImg${id}" name="slide_image_${id}" class="form-control" accept="image/png,image/jpeg,image/webp">
            <div class="form-text">Current: ${esc(s.image_path || 'none')}</div></div>
        </div></div>
      </div></div>`;
	}

	async function loadSettings() {
		try {
			const data = await api('settings_get');
			Object.entries(data.settings).forEach(([name, value]) => {
				const input = settingsForm.elements[name];
				if (!input || input.type === 'file') return;
				if (input.type === 'checkbox') input.checked = value === '1';
				else input.value = value ?? '';
			});
			$('#logoCurrent').textContent = data.settings.event_logo
				? `Current: ${data.settings.event_logo}`
				: 'No logo uploaded yet.';
			$('#slideAccordion').innerHTML = data.slides.map(slideHtml).join('');
		} catch (error) {
			fail(error);
		}
	}

	settingsForm.addEventListener('submit', async event => {
		event.preventDefault();
		const data = new FormData(settingsForm);
		data.set('registration_open', settingsForm.elements.registration_open.checked ? '1' : '0');
		data.set('abstract_open', settingsForm.elements.abstract_open.checked ? '1' : '0');
		const button = $('#settingsSave');
		button.disabled = true;
		try {
			await api('settings_save', { method: 'POST', body: data });
			toast('success', 'Settings saved');
			loadSettings();
		} catch (error) {
			fail(error);
		} finally {
			button.disabled = false;
		}
	});

	/* ==========================================================
     Change password
     ========================================================== */
	const passwordForm = $('#passwordForm');
	passwordForm.addEventListener('submit', async event => {
		event.preventDefault();
		const confirmField = $('#pwConfirm');
		confirmField.setCustomValidity(
			$('#pwNew').value === confirmField.value ? '' : 'Passwords do not match'
		);
		passwordForm.classList.add('was-validated');
		if (!passwordForm.checkValidity()) return;
		try {
			await api('change_password', { method: 'POST', body: new FormData(passwordForm) });
			toast('success', 'Password updated');
			passwordForm.reset();
			passwordForm.classList.remove('was-validated');
		} catch (error) {
			fail(error);
		}
	});

	/* ==========================================================
     Start
     ========================================================== */
	showView(location.hash.replace('#', '') || 'dashboard');
	loadStats().catch(fail);
	Object.values(lists).forEach(list => list.load());
	loadSettings();
	pollCounts();
	setInterval(pollCounts, 30000);
})();
