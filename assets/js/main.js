/* ==========================================================
   main.js
   1. Shared data and helpers
   2. AOS and footer year
   3. Navbar
   4. Hero slider (index page only)
   5. Registration form (registration page only)
   ========================================================== */

/* ---------- 1. Shared data and helpers ---------- */
window.appData = {
	countries: [
		['India', '+91'],
		['Pakistan', '+92'],
		['Bangladesh', '+880'],
		['Sri Lanka', '+94'],
		['Nepal', '+977'],
		['United States', '+1'],
		['Canada', '+1'],
		['United Kingdom', '+44'],
		['Ireland', '+353'],
		['Australia', '+61'],
		['New Zealand', '+64'],
		['Germany', '+49'],
		['France', '+33'],
		['Italy', '+39'],
		['Spain', '+34'],
		['Netherlands', '+31'],
		['Switzerland', '+41'],
		['Sweden', '+46'],
		['Norway', '+47'],
		['Denmark', '+45'],
		['Russia', '+7'],
		['Turkey', '+90'],
		['China', '+86'],
		['Japan', '+81'],
		['South Korea', '+82'],
		['Singapore', '+65'],
		['Malaysia', '+60'],
		['Indonesia', '+62'],
		['Thailand', '+66'],
		['Vietnam', '+84'],
		['Philippines', '+63'],
		['United Arab Emirates', '+971'],
		['Saudi Arabia', '+966'],
		['Qatar', '+974'],
		['Kuwait', '+965'],
		['Oman', '+968'],
		['Egypt', '+20'],
		['Nigeria', '+234'],
		['Kenya', '+254'],
		['South Africa', '+27'],
		['Brazil', '+55'],
		['Mexico', '+52'],
		['Argentina', '+54'],
	],

	fillCountrySelects(countrySelect, codeSelect) {
		if (countrySelect) {
			countrySelect.innerHTML =
				'<option value="" selected disabled>Select country</option>' +
				this.countries.map(([name]) => `<option>${name}</option>`).join('');
		}
		if (codeSelect) {
			codeSelect.innerHTML = this.countries
				.map(
					([name, dial], i) =>
						`<option value="${dial}" ${i === 0 ? 'selected' : ''}>${dial} ${name}</option>`
				)
				.join('');
		}
	},

	escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	},

	// Gets a CSRF token (and open/closed status) from a form endpoint
	async getToken(url) {
		const response = await fetch(url, { headers: { Accept: 'application/json' } });
		const data = await response.json().catch(() => null);
		if (!response.ok || !data || !data.success) {
			throw new Error((data && data.message) || 'Could not reach the server.');
		}
		return data.token;
	},
};

/* ---------- 2. AOS and footer year ---------- */
document.addEventListener('DOMContentLoaded', () => {
	if (window.AOS) {
		AOS.init({
			duration: 700,
			once: true,
			offset: 40,
			disable: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
		});
	}
	const year = document.getElementById('footerYear');
	if (year) year.textContent = new Date().getFullYear();
});

/* ---------- 3. Navbar ---------- */
(() => {
	'use strict';

	const navbar = document.getElementById('siteNavbar');
	const navMenu = document.getElementById('navMenu');
	if (!navbar || !navMenu) return;

	const outlineBtn = navbar.querySelector('.navBtnOutline');
	const alwaysSolid = document.body.dataset.solidNav === 'true';
	let menuOpen = false;

	// Transparent over the hero; solid white after scrolling, when the mobile menu is open,
	// or on pages that set data-solid-nav="true".
	const renderNavbar = () => {
		const solid = alwaysSolid || window.scrollY > 50 || menuOpen;
		navbar.classList.toggle('bg-white', solid);
		navbar.classList.toggle('shadow-sm', solid);
		navbar.dataset.bsTheme = solid ? 'light' : 'dark';
		if (outlineBtn) {
			outlineBtn.classList.toggle('btn-outline-light', !solid);
			outlineBtn.classList.toggle('btn-outline-primary', solid);
		}
	};

	window.addEventListener('scroll', renderNavbar, { passive: true });

	navMenu.addEventListener('show.bs.collapse', () => {
		menuOpen = true;
		renderNavbar();
	});
	navMenu.addEventListener('hidden.bs.collapse', () => {
		menuOpen = false;
		renderNavbar();
	});

	// Close the mobile menu after selecting a link
	navMenu.querySelectorAll('a').forEach(link => {
		link.addEventListener('click', () => {
			const instance = bootstrap.Collapse.getInstance(navMenu);
			if (instance && navMenu.classList.contains('show')) instance.hide();
		});
	});

	renderNavbar();
})();

/* ---------- 4. Hero slider (index page only) ---------- */
(() => {
	'use strict';

	if (!document.querySelector('.heroSwiper')) return;

	new Swiper('.heroSwiper', {
		slidesPerView: 1,
		loop: true,
		speed: 1100,
		grabCursor: true,

		keyboard: {
			enabled: true,
		},

		autoplay: {
			delay: 6000,
			disableOnInteraction: false,
			pauseOnMouseEnter: false,
			stopOnLastSlide: false,
		},

		navigation: {
			nextEl: '.heroNext',
			prevEl: '.heroPrev',
		},

		pagination: {
			el: '.heroPagination',
			clickable: true,

			renderBullet: (index, className) =>
				`<button type="button"
                    class="${className} heroBullet"
                    aria-label="Go to slide ${index + 1}">
                    <span class="bulletFill"></span>
                </button>`,
		},

		on: {
			// Yellow progress bar animation
			autoplayTimeLeft(swiper, time, progress) {
				const bullets = swiper.pagination?.bullets;

				if (!bullets) return;

				bullets.forEach(bullet => {
					const fill = bullet.querySelector('.bulletFill');

					if (!fill) return;

					const isActive = bullet.classList.contains('swiper-pagination-bullet-active');

					fill.style.width = isActive ? `${(1 - progress) * 100}%` : '0%';
				});
			},

			// Outgoing text animation
			slideChangeTransitionStart(swiper) {
				const leavingSlide = swiper.slides[swiper.previousIndex];

				if (leavingSlide && swiper.previousIndex !== swiper.activeIndex) {
					leavingSlide.classList.add('textOut');
				}
			},

			// Reset text animation
			slideChangeTransitionEnd(swiper) {
				swiper.slides.forEach(slide => {
					slide.classList.remove('textOut');
				});
			},
		},
	});
})();
/* ---------- 5. Registration form (registration page only) ---------- */
(() => {
	'use strict';

	const form = document.getElementById('registrationForm');
	if (!form) return;

	// false = real PHP backend use hoga
	const demoMode = false;
	const endpoint = 'api/registration-submit.php';
	const rateError = document.getElementById('regRateError');
	const participants = document.getElementById('regParticipants');
	const summary = document.getElementById('regSummary');
	const usd = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
	const formatPrice = cents => Number.isFinite(cents) ? usd.format(cents / 100) : '—';

	const updateSummary = () => {
		const rate = form.querySelector('[name="registration_rate"]:checked');
		const [tier, category] = rate ? rate.value.split('|') : ['—', 'Select a category'];
		const price = Number(rate?.dataset.priceCents);
		const count = participants.validity.valid ? participants.valueAsNumber : NaN;
		const subtotal = price * count;
		const gst = Math.round(subtotal / 10);
		const values = {
			category,
			tier,
			price: formatPrice(price),
			count: Number.isFinite(count) ? String(count) : '—',
			subtotal: formatPrice(subtotal),
			gst: formatPrice(gst),
			total: formatPrice(subtotal + gst),
		};
		for (const field of summary.querySelectorAll('[data-summary]')) {
			field.textContent = values[field.dataset.summary];
		}
	};
	form.addEventListener('change', event => {
		if (event.target.name === 'registration_rate') {
			rateError.hidden = true;
			updateSummary();
		}
	});
	participants.addEventListener('input', updateSummary);
	form.addEventListener('reset', () => {
		form.classList.remove('was-validated');
		rateError.hidden = true;
		queueMicrotask(updateSummary);
	});
	window.addEventListener('pageshow', updateSummary);
	updateSummary();

	const submitBtn = document.getElementById('regSubmit');
	const resetBtn = document.getElementById('regReset');
	const spinner = submitBtn.querySelector('.spinner-border');
	const btnLabel = submitBtn.querySelector('.btnLabel');
	let isSubmitting = false;

	appData.fillCountrySelects(
		document.getElementById('regCountry'),
		document.getElementById('regPhoneCode')
	);

	const setLoading = loading => {
		submitBtn.disabled = loading;
		resetBtn.disabled = loading;
		spinner.classList.toggle('d-none', !loading);
		btnLabel.textContent = loading ? 'Submitting...' : 'Pay';
	};

	// Temporary fake response so the UI can be tested before the PHP backend exists
	const fakeSubmit = () =>
		new Promise(resolve => {
			setTimeout(
				() =>
					resolve({
						success: true,
						reference:
							'REG-2027-' + Math.random().toString(36).slice(2, 8).toUpperCase(),
					}),
				1200
			);
		});

	// XMLHttpRequest isliye, kyunke fetch upload progress nahi dikha sakta
	const sendToServer = async () => {
		const body = new FormData(form);
		const token = await appData.getToken(endpoint);
		body.append('csrf_token', token);

		const response = await fetch(endpoint, { method: 'POST', body });
		const data = await response.json().catch(() => null);
		if (!data) throw new Error('Server error. Please try again.');
		if (!response.ok || !data.success) throw new Error(data.message || 'Submission failed.');
		return data;
	};

	form.addEventListener('submit', async event => {
		event.preventDefault();
		if (isSubmitting) return; // block duplicate submissions

		form.classList.add('was-validated');
		rateError.hidden = Boolean(form.querySelector('[name="registration_rate"]:checked'));
		if (!form.checkValidity()) {
			const firstInvalid = form.querySelector(':invalid');
			if (firstInvalid) firstInvalid.focus();
			return;
		}

		isSubmitting = true;
		setLoading(true);

		try {
			const result = demoMode ? await fakeSubmit() : await sendToServer();
			if (!result.success) throw new Error(result.message || 'Submission failed.');

			await Swal.fire({
				icon: 'success',
				title: 'Registration Received',
			html: `<p class="mb-1">The conference team will follow up with payment instructions. Please keep your reference ID:</p>
               <div class="refBox">${appData.escapeHtml(result.reference)}</div>`,
				confirmButtonText: 'Done',
				confirmButtonColor: '#012059',
			});

			form.reset();
			form.classList.remove('was-validated');
		} catch (error) {
			Swal.fire({
				icon: 'error',
				title: 'Could not submit',
				text: error.message,
				confirmButtonColor: '#012059',
			});
		} finally {
			isSubmitting = false;
			setLoading(false);
		}
	});
})();

// ---------------

/* ---------- 6. Abstract form (abstract page only) ---------- */
(() => {
	'use strict';

	const form = document.getElementById('abstractForm');
	if (!form) return;

	// Set to false once api/abstract-submit.php exists
	const demoMode = false;
	const endpoint = 'api/abstract-submit.php';

	const maxFileBytes = 5 * 1024 * 1024;
	const allowedExtensions = ['pdf', 'doc', 'docx'];

	const submitBtn = document.getElementById('absSubmit');
	const spinner = submitBtn.querySelector('.spinner-border');
	const btnLabel = submitBtn.querySelector('.btnLabel');
	const description = document.getElementById('absDescription');
	const wordCounter = document.getElementById('wordCounter');
	const fileInput = document.getElementById('absFile');
	const fileError = document.getElementById('fileError');
	const fileInfo = document.getElementById('fileInfo');
	const fileName = document.getElementById('fileName');
	const fileSize = document.getElementById('fileSize');
	const progressWrap = document.getElementById('uploadProgressWrap');
	const progressBar = document.getElementById('uploadProgressBar');
	const wordLimit = parseInt(description.dataset.wordLimit, 10) || 300;
	let isSubmitting = false;

	appData.fillCountrySelects(
		document.getElementById('absCountry'),
		document.getElementById('absPhoneCode')
	);

	/* ----- Live word counter ----- */
	const countWords = text => (text.trim() ? text.trim().split(/\s+/).length : 0);

	const updateWordCounter = () => {
		const words = countWords(description.value);
		wordCounter.textContent = `${words} / ${wordLimit} words`;
		wordCounter.classList.toggle('counterWarn', words > wordLimit * 0.9 && words <= wordLimit);
		wordCounter.classList.toggle('counterOver', words > wordLimit);
		description.classList.toggle('is-over', words > wordLimit);
		description.setCustomValidity(words > wordLimit ? 'Word limit exceeded' : '');
	};
	description.addEventListener('input', updateWordCounter);
	updateWordCounter();

	/* ----- File validation ----- */
	const formatBytes = bytes =>
		bytes < 1024 * 1024
			? `${(bytes / 1024).toFixed(0)} KB`
			: `${(bytes / 1024 / 1024).toFixed(2)} MB`;

	const validateFile = () => {
		const file = fileInput.files[0];
		fileInfo.classList.add('d-none');

		if (!file) {
			fileInput.setCustomValidity('required');
			fileError.textContent = 'Please upload your abstract file.';
			return false;
		}

		const extension = file.name.split('.').pop().toLowerCase();
		if (!allowedExtensions.includes(extension)) {
			fileInput.setCustomValidity('type');
			fileError.textContent = 'Only PDF, DOC and DOCX files are allowed.';
			return false;
		}
		if (file.size > maxFileBytes) {
			fileInput.setCustomValidity('size');
			fileError.textContent = `File is too large (${formatBytes(file.size)}). Maximum is 5 MB.`;
			return false;
		}
		if (file.size === 0) {
			fileInput.setCustomValidity('empty');
			fileError.textContent = 'The selected file is empty.';
			return false;
		}

		fileInput.setCustomValidity('');
		fileName.textContent = file.name;
		fileSize.textContent = `(${formatBytes(file.size)})`;
		fileInfo.classList.remove('d-none');
		return true;
	};

	fileInput.addEventListener('change', () => {
		validateFile();
		form.classList.contains('was-validated') && fileInput.reportValidity();
	});

	/* ----- Progress and loading helpers ----- */
	const setProgress = percent => {
		progressBar.style.width = `${percent}%`;
		progressWrap.setAttribute('aria-valuenow', percent);
	};

	const setLoading = loading => {
		submitBtn.disabled = loading;
		spinner.classList.toggle('d-none', !loading);
		btnLabel.textContent = loading ? 'Uploading...' : 'Submit Abstract';
		progressWrap.classList.toggle('d-none', !loading);
		if (!loading) setProgress(0);
	};

	/* ----- Submission ----- */
	// Temporary fake upload so the UI can be tested before the PHP backend exists
	const fakeSubmit = () =>
		new Promise(resolve => {
			let percent = 0;
			const timer = setInterval(() => {
				percent += 10;
				setProgress(percent);
				if (percent >= 100) {
					clearInterval(timer);
					resolve({
						success: true,
						reference:
							'ABS-2027-' + Math.random().toString(36).slice(2, 8).toUpperCase(),
					});
				}
			}, 150);
		});

	// XMLHttpRequest is used here (instead of fetch) because fetch cannot report upload progress
	const sendToServer = async () => {
		const token = await appData.getToken(endpoint);
		const body = new FormData(form);
		body.append('csrf_token', token);

		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			xhr.open('POST', endpoint);
			xhr.setRequestHeader('Accept', 'application/json');
			xhr.upload.onprogress = event => {
				if (event.lengthComputable)
					setProgress(Math.round((event.loaded / event.total) * 100));
			};
			xhr.onload = () => {
				let data = null;
				try {
					data = JSON.parse(xhr.responseText);
				} catch (err) {
					/* handled below */
				}
				if (data && data.success) resolve(data);
				else reject(new Error((data && data.message) || 'Server error. Please try again.'));
			};
			xhr.onerror = () => reject(new Error('Network error. Please check your connection.'));
			xhr.send(body);
		});
	};
	form.addEventListener('submit', async event => {
		event.preventDefault();
		if (isSubmitting) return; // block duplicate submissions

		updateWordCounter();
		validateFile();
		form.classList.add('was-validated');

		if (!form.checkValidity()) {
			const firstInvalid = form.querySelector(':invalid');
			if (firstInvalid) firstInvalid.focus();
			return;
		}

		isSubmitting = true;
		setLoading(true);

		try {
			const result = demoMode ? await fakeSubmit() : await sendToServer();
			if (!result.success) throw new Error(result.message || 'Submission failed.');

			await Swal.fire({
				icon: 'success',
				title: 'Abstract Submitted',
				html: `<p class="mb-1">Thank you! Your abstract is now <strong>Pending</strong> review.<br>Please keep your reference ID:</p>
               <div class="refBox">${appData.escapeHtml(result.reference)}</div>`,
				confirmButtonText: 'Done',
				confirmButtonColor: '#012059',
			});

			form.reset();
			form.classList.remove('was-validated');
			fileInfo.classList.add('d-none');
			updateWordCounter();
		} catch (error) {
			Swal.fire({
				icon: 'error',
				title: 'Could not submit',
				text: error.message,
				confirmButtonColor: '#012059',
			});
		} finally {
			isSubmitting = false;
			setLoading(false);
		}
	});
})();

// ---------------------

/* ---------- 7. Admin panel (admin-panel.html only) ---------- */
(() => {
	'use strict';

	if (!document.body.classList.contains('adminBody')) return;

	const esc = value => appData.escapeHtml(String(value ?? ''));
	const $ = id => document.getElementById(id);

	/* ===== DEMO DATA (replaced by database results in the PHP phase) ===== */
	const people = [
		['Dr.', 'Aisha', 'Khan', 'Pakistan', 'Aga Khan University'],
		['Prof.', 'Rahul', 'Sharma', 'India', 'IIT Delhi'],
		['Ms.', 'Emily', 'Carter', 'United Kingdom', 'University of Oxford'],
		['Mr.', 'Liam', 'Walker', 'Australia', 'University of Sydney'],
		['Dr.', 'Sofia', 'Rossi', 'Italy', 'University of Milan'],
		['Dr.', 'Hiro', 'Tanaka', 'Japan', 'Kyoto University'],
		['Ms.', 'Fatima', 'Al Mansoori', 'United Arab Emirates', 'Khalifa University'],
		['Mr.', 'Daniel', 'Weber', 'Germany', 'TU Munich'],
		['Dr.', 'Priya', 'Nair', 'India', 'AIIMS'],
		['Prof.', 'John', 'Smith', 'United States', 'MIT'],
		['Ms.', 'Chloe', 'Dubois', 'France', 'Sorbonne University'],
		['Dr.', 'Ahmed', 'Hassan', 'Egypt', 'Cairo University'],
	];
	const categories = [
		'Delegate',
		'Speaker',
		'Student',
		'Researcher',
		'Academic',
		'Industry Professional',
		'Other',
	];
	const regStatuses = ['Pending', 'Pending', 'Confirmed', 'Confirmed', 'Rejected'];
	const tracks = [
		'Life Sciences',
		'Physical Sciences',
		'Engineering and Technology',
		'Medical and Health Sciences',
		'Environmental Sciences',
		'Computer Science and AI',
	];
	const presTypes = ['Oral Presentation', 'Poster Presentation', 'Virtual Presentation'];
	const absStatuses = [
		'Pending',
		'Under Review',
		'Accepted',
		'Rejected',
		'Revision Required',
		'Pending',
	];
	const absTitles = [
		'Machine Learning for Early Disease Detection',
		'Microplastics in Freshwater Ecosystems',
		'Graphene-Based Energy Storage Devices',
		'CRISPR Applications in Crop Resilience',
		'Quantum Sensors for Medical Imaging',
		'Urban Air Quality Forecasting With Neural Networks',
		'Biodegradable Polymers From Agricultural Waste',
		'Gut Microbiome and Mental Health',
		'Low-Cost Water Purification Systems',
		'Satellite Data for Glacier Monitoring',
		'Protein Folding Prediction Advances',
		'Solar Cell Efficiency Through Perovskites',
		'Antimicrobial Resistance Surveillance',
		'Edge Computing in Smart Healthcare',
		'Coral Reef Restoration Techniques',
		'Deep Learning in Radiology Reports',
	];

	const daysAgo = n => {
		const d = new Date();
		d.setDate(d.getDate() - n);
		return d.toISOString().slice(0, 10);
	};
	const pad = (n, len = 4) => String(n).padStart(len, '0');

	const registrations = Array.from({ length: 26 }, (_, i) => {
		const [title, first, last, country, org] = people[i % people.length];
		const ago = i * 2 + 1;
		return {
			id: i + 1,
			ref: `REG-2027-${pad(i + 1)}`,
			title,
			first,
			last,
			country,
			org,
			email: `${first}.${last}${i}@example.com`.toLowerCase().replace(/\s+/g, ''),
			phone: `+${10 + (i % 80)} 98${pad((i * 137) % 10000)}${pad((i * 71) % 100, 2)}`,
			designation: i % 2 ? 'Research Scholar' : 'Associate Professor',
			category: categories[i % categories.length],
			type: i % 3 === 0 ? 'Virtual' : 'In-person',
			diet: i % 4 === 0 ? 'Vegetarian' : '',
			message: i % 5 === 0 ? 'Looking forward to attending.' : '',
			date: daysAgo(ago),
			ago,
			status: regStatuses[i % regStatuses.length],
		};
	});

	const abstracts = Array.from({ length: 18 }, (_, i) => {
		const [title, first, last, country, org] = people[(i + 3) % people.length];
		const ago = i * 3 + 1;
		return {
			id: i + 1,
			ref: `ABS-2027-${pad(i + 1)}`,
			title,
			first,
			last,
			country,
			org,
			email: `${first}.${last}${i}@example.com`.toLowerCase().replace(/\s+/g, ''),
			abstractTitle: absTitles[i % absTitles.length],
			track: tracks[i % tracks.length],
			type: presTypes[i % presTypes.length],
			keywords: 'research, innovation, analysis',
			coAuthors: i % 3 === 0 ? 'Jane Doe, University of Example' : '',
			description:
				'This study investigates new approaches in the selected research area. ' +
				'Methods include experimental validation and comparative analysis across multiple datasets. ' +
				'Results indicate meaningful improvements over existing baselines, with implications for future work.',
			file: `abstract-${pad(i + 1)}.pdf`,
			notes: '',
			date: daysAgo(ago),
			ago,
			status: absStatuses[i % absStatuses.length],
		};
	});

	/* ===== Helpers ===== */
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
	const fullName = r => `${r.title} ${r.first} ${r.last}`;
	const toast = (icon, title) =>
		Swal.fire({
			toast: true,
			position: 'top-end',
			icon,
			title,
			showConfirmButton: false,
			timer: 2200,
		});

	/* ===== Sidebar and views ===== */
	const body = document.body;
	const viewTitles = {
		dashboard: 'Dashboard',
		registrations: 'Registrations',
		abstracts: 'Abstract Submissions',
		settings: 'Event Settings',
		password: 'Change Password',
	};

	$('sidebarToggle').addEventListener('click', () => {
		if (window.innerWidth >= 992) body.classList.toggle('adminCollapsed');
		else body.classList.toggle('sidebarOpen');
	});
	$('sidebarBackdrop').addEventListener('click', () => body.classList.remove('sidebarOpen'));

	const showView = name => {
		if (!viewTitles[name]) name = 'dashboard';
		document
			.querySelectorAll('.adminView')
			.forEach(el => el.classList.toggle('d-none', el.id !== `view-${name}`));
		document
			.querySelectorAll('.sidebarLink[data-view]')
			.forEach(link => link.classList.toggle('active', link.dataset.view === name));
		$('breadcrumbCurrent').textContent = viewTitles[name];
		body.classList.remove('sidebarOpen');
		window.scrollTo({ top: 0 });
		if (name === 'dashboard') renderCharts();
	};

	document.querySelectorAll('[data-view], [data-view-link]').forEach(link => {
		link.addEventListener('click', event => {
			event.preventDefault();
			const name = link.dataset.view || link.dataset.viewLink;
			history.replaceState(null, '', `#${name}`);
			showView(name);
		});
	});

	const logout = event => {
		event.preventDefault();
		Swal.fire({
			icon: 'question',
			title: 'Log out?',
			showCancelButton: true,
			confirmButtonText: 'Logout',
			confirmButtonColor: '#012059',
		}).then(result => {
			if (result.isConfirmed) toast('info', 'Logout will work once the PHP login is built');
		});
	};
	$('logoutLink').addEventListener('click', logout);
	$('logoutMenu').addEventListener('click', logout);

	/* ===== Dashboard: stat cards ===== */
	const countBy = (list, key, value) => list.filter(item => item[key] === value).length;

	const renderStats = () => {
		const cards = [
			['Total Registrations', registrations.length, 'fa-users', 'primary'],
			['Total Abstracts', abstracts.length, 'fa-file-lines', 'secondary'],
			[
				'Pending Registrations',
				countBy(registrations, 'status', 'Pending'),
				'fa-hourglass-half',
				'warning',
			],
			[
				'Confirmed Registrations',
				countBy(registrations, 'status', 'Confirmed'),
				'fa-circle-check',
				'success',
			],
			['Pending Abstracts', countBy(abstracts, 'status', 'Pending'), 'fa-clock', 'warning'],
			[
				'Accepted Abstracts',
				countBy(abstracts, 'status', 'Accepted'),
				'fa-check-double',
				'success',
			],
			[
				'Rejected Abstracts',
				countBy(abstracts, 'status', 'Rejected'),
				'fa-circle-xmark',
				'danger',
			],
		];
		$('statCards').innerHTML = cards
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

		const pendingRegs = countBy(registrations, 'status', 'Pending');
		const pendingAbs =
			countBy(abstracts, 'status', 'Pending') + countBy(abstracts, 'status', 'Under Review');
		$('notifyRegs').textContent = `${pendingRegs} pending registrations`;
		$('notifyAbs').textContent = `${pendingAbs} abstracts awaiting review`;
		const count = $('notifyCount');
		count.textContent = pendingRegs + pendingAbs;
		count.classList.toggle('d-none', pendingRegs + pendingAbs === 0);
	};

	const renderRecent = () => {
		const recentRegs = [...registrations].sort((a, b) => a.ago - b.ago).slice(0, 5);
		$('recentRegs').innerHTML = recentRegs
			.map(
				r =>
					`<tr><td>${esc(r.ref)}</td><td>${esc(fullName(r))}</td><td>${badge(r.status)}</td></tr>`
			)
			.join('');

		const recentAbs = [...abstracts].sort((a, b) => a.ago - b.ago).slice(0, 5);
		$('recentAbs').innerHTML = recentAbs
			.map(
				a =>
					`<tr><td>${esc(a.ref)}</td><td class="wrapCell">${esc(a.abstractTitle)}</td><td>${badge(a.status)}</td></tr>`
			)
			.join('');
	};

	/* ===== Dashboard: charts ===== */
	const charts = {};
	const weeklyCounts = list => {
		const weeks = 8;
		const counts = Array(weeks).fill(0);
		list.forEach(item => {
			const w = Math.floor(item.ago / 7);
			if (w < weeks) counts[weeks - 1 - w] += 1;
		});
		return counts;
	};
	const weekLabels = Array.from({ length: 8 }, (_, i) =>
		i === 7 ? 'This week' : `${7 - i}w ago`
	);
	const palette = ['#012059', '#2563EB', '#FFC928', '#10B981', '#F97316', '#8B5CF6', '#94A3B8'];

	const drawChart = (id, config) => {
		if (charts[id]) charts[id].destroy();
		charts[id] = new Chart($(id), config);
	};

	const renderCharts = () => {
		if (!window.Chart) return;
		const baseOptions = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: { legend: { display: false } },
		};

		drawChart('chartRegTrend', {
			type: 'line',
			data: {
				labels: weekLabels,
				datasets: [
					{
						label: 'Registrations',
						data: weeklyCounts(registrations),
						borderColor: '#2563EB',
						backgroundColor: 'rgba(37,99,235,.12)',
						fill: true,
						tension: 0.35,
					},
				],
			},
			options: {
				...baseOptions,
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		});

		drawChart('chartAbsTrend', {
			type: 'bar',
			data: {
				labels: weekLabels,
				datasets: [
					{
						label: 'Abstracts',
						data: weeklyCounts(abstracts),
						backgroundColor: '#FFC928',
						borderRadius: 6,
					},
				],
			},
			options: {
				...baseOptions,
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		});

		const countryMap = {};
		registrations.forEach(r => {
			countryMap[r.country] = (countryMap[r.country] || 0) + 1;
		});
		const topCountries = Object.entries(countryMap)
			.sort((a, b) => b[1] - a[1])
			.slice(0, 7);
		drawChart('chartCountry', {
			type: 'doughnut',
			data: {
				labels: topCountries.map(c => c[0]),
				datasets: [{ data: topCountries.map(c => c[1]), backgroundColor: palette }],
			},
			options: { ...baseOptions, plugins: { legend: { display: true, position: 'right' } } },
		});

		drawChart('chartCategory', {
			type: 'bar',
			data: {
				labels: categories,
				datasets: [
					{
						label: 'Registrations',
						data: categories.map(c => countBy(registrations, 'category', c)),
						backgroundColor: '#012059',
						borderRadius: 6,
					},
				],
			},
			options: {
				...baseOptions,
				indexAxis: 'y',
				scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		});
	};

	/* ===== Generic table helper: search, filter, pagination ===== */
	const pageSize = 8;

	const buildTable = ({
		list,
		tbody,
		infoEl,
		pagerEl,
		searchEl,
		filters,
		searchKeys,
		rowHtml,
	}) => {
		let page = 1;

		const filtered = () => {
			const q = searchEl.value.trim().toLowerCase();
			return list.filter(
				item =>
					(!q || searchKeys(item).some(text => text.toLowerCase().includes(q))) &&
					filters.every(([el, key]) => !el.value || item[key] === el.value)
			);
		};

		const render = () => {
			const rows = filtered();
			const pages = Math.max(1, Math.ceil(rows.length / pageSize));
			page = Math.min(page, pages);
			const start = (page - 1) * pageSize;
			const visible = rows.slice(start, start + pageSize);

			tbody.innerHTML = visible.length
				? visible.map((item, i) => rowHtml(item, start + i + 1)).join('')
				: '<tr><td colspan="12" class="text-center text-body-secondary py-4">No records found.</td></tr>';

			infoEl.textContent = rows.length
				? `Showing ${start + 1}–${start + visible.length} of ${rows.length}`
				: '0 results';
			pagerEl.innerHTML =
				`<button class="btn btn-outline-secondary btn-sm" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}>Prev</button>` +
				`<button class="btn btn-outline-secondary btn-sm disabled">${page} / ${pages}</button>` +
				`<button class="btn btn-outline-secondary btn-sm" data-page="${page + 1}" ${page === pages ? 'disabled' : ''}>Next</button>`;
		};

		pagerEl.addEventListener('click', event => {
			const btn = event.target.closest('[data-page]');
			if (btn && !btn.disabled) {
				page = Number(btn.dataset.page);
				render();
			}
		});
		[searchEl, ...filters.map(([el]) => el)].forEach(el =>
			el.addEventListener('input', () => {
				page = 1;
				render();
			})
		);

		return { render, filtered };
	};

	/* ===== CSV export (with formula-injection protection) ===== */
	const csvCell = value => {
		let text = String(value ?? '');
		if (/^[=+\-@\t\r]/.test(text)) text = `'${text}`;
		return `"${text.replace(/"/g, '""')}"`;
	};
	const downloadCsv = (filename, headers, rows) => {
		const csv = [headers, ...rows].map(row => row.map(csvCell).join(',')).join('\r\n');
		const link = document.createElement('a');
		link.href = URL.createObjectURL(
			new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' })
		);
		link.download = filename;
		link.click();
		URL.revokeObjectURL(link.href);
	};

	/* ===== Registrations table ===== */
	const regCategoryFilter = $('regCategoryFilter');
	regCategoryFilter.innerHTML += categories.map(c => `<option>${esc(c)}</option>`).join('');

	const regTable = buildTable({
		list: registrations,
		tbody: $('regTable'),
		infoEl: $('regInfo'),
		pagerEl: $('regPager'),
		searchEl: $('regSearch'),
		filters: [
			[$('regStatusFilter'), 'status'],
			[regCategoryFilter, 'category'],
		],
		searchKeys: r => [fullName(r), r.email, r.ref],
		rowHtml: (r, n) => `
      <tr>
        <td>${n}</td><td class="fw-semibold">${esc(r.ref)}</td><td>${esc(fullName(r))}</td><td>${esc(r.email)}</td>
        <td>${esc(r.country)}</td><td>${esc(r.category)}</td><td>${esc(r.type)}</td><td>${esc(r.date)}</td>
        <td>${badge(r.status)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary actionBtn" data-act="view" data-id="${r.id}" aria-label="View ${esc(r.ref)}"><i class="fa-solid fa-eye"></i></button>
          <button class="btn btn-sm btn-outline-success actionBtn" data-act="confirm" data-id="${r.id}" aria-label="Confirm ${esc(r.ref)}"><i class="fa-solid fa-check"></i></button>
          <button class="btn btn-sm btn-outline-danger actionBtn" data-act="delete" data-id="${r.id}" aria-label="Delete ${esc(r.ref)}"><i class="fa-solid fa-trash"></i></button>
        </td>
      </tr>`,
	});

	/* ===== Abstracts table ===== */
	const absTrackFilter = $('absTrackFilter');
	absTrackFilter.innerHTML += tracks.map(t => `<option>${esc(t)}</option>`).join('');

	const absTable = buildTable({
		list: abstracts,
		tbody: $('absTable'),
		infoEl: $('absInfo'),
		pagerEl: $('absPager'),
		searchEl: $('absSearch'),
		filters: [
			[$('absStatusFilter'), 'status'],
			[absTrackFilter, 'track'],
		],
		searchKeys: a => [fullName(a), a.abstractTitle, a.ref],
		rowHtml: (a, n) => `
      <tr>
        <td>${n}</td><td class="fw-semibold">${esc(a.ref)}</td><td>${esc(fullName(a))}</td>
        <td class="wrapCell">${esc(a.abstractTitle)}</td><td>${esc(a.track)}</td><td>${esc(a.type)}</td>
        <td>${esc(a.date)}</td><td>${badge(a.status)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary actionBtn" data-act="view" data-id="${a.id}" aria-label="Review ${esc(a.ref)}"><i class="fa-solid fa-eye"></i></button>
          <button class="btn btn-sm btn-outline-secondary actionBtn" data-act="download" data-id="${a.id}" aria-label="Download file for ${esc(a.ref)}"><i class="fa-solid fa-download"></i></button>
          <button class="btn btn-sm btn-outline-danger actionBtn" data-act="delete" data-id="${a.id}" aria-label="Delete ${esc(a.ref)}"><i class="fa-solid fa-trash"></i></button>
        </td>
      </tr>`,
	});

	/* ===== Detail modal ===== */
	const detailModal = new bootstrap.Modal($('detailModal'));
	const detailTitle = $('detailTitle');
	const detailBody = $('detailBody');
	const detailFooter = $('detailFooter');

	const detailRow = (label, value) =>
		`<dt class="col-sm-4">${esc(label)}</dt><dd class="col-sm-8">${esc(value) || '<span class="text-body-secondary">–</span>'}</dd>`;

	const refreshAll = () => {
		renderStats();
		renderRecent();
		regTable.render();
		absTable.render();
		renderCharts();
	};

	const openRegistration = record => {
		detailTitle.textContent = `Registration ${record.ref}`;
		detailBody.innerHTML = `<dl class="row detailList mb-0">
      ${detailRow('Name', fullName(record))}${detailRow('Email', record.email)}${detailRow('Phone', record.phone)}
      ${detailRow('Country', record.country)}${detailRow('Organization', record.org)}${detailRow('Designation', record.designation)}
      ${detailRow('Category', record.category)}${detailRow('Participation', record.type)}
      ${detailRow('Dietary needs', record.diet)}${detailRow('Message', record.message)}${detailRow('Submitted', record.date)}
      <dt class="col-sm-4">Status</dt><dd class="col-sm-8">${badge(record.status)}</dd></dl>`;
		detailFooter.innerHTML = `
      <button class="btn btn-success" data-set="Confirmed">Confirm</button>
      <button class="btn btn-danger" data-set="Rejected">Reject</button>
      <button class="btn btn-light border" data-bs-dismiss="modal">Close</button>`;
		detailFooter.onclick = event => {
			const btn = event.target.closest('[data-set]');
			if (!btn) return;
			record.status = btn.dataset.set;
			refreshAll();
			detailModal.hide();
			toast('success', `Registration ${record.status.toLowerCase()}`);
		};
		detailModal.show();
	};

	const openAbstract = record => {
		detailTitle.textContent = `Abstract ${record.ref}`;
		detailBody.innerHTML = `<dl class="row detailList">
      ${detailRow('Author', fullName(record))}${detailRow('Email', record.email)}${detailRow('Organization', record.org)}
      ${detailRow('Country', record.country)}${detailRow('Title', record.abstractTitle)}${detailRow('Track', record.track)}
      ${detailRow('Presentation', record.type)}${detailRow('Co-authors', record.coAuthors)}${detailRow('Keywords', record.keywords)}
      ${detailRow('File', record.file)}${detailRow('Submitted', record.date)}</dl>
      <h3 class="h6 fw-bold">Abstract</h3>
      <div class="abstractText mb-3">${esc(record.description)}</div>
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label fw-semibold" for="absStatusSelect">Status</label>
          <select id="absStatusSelect" class="form-select">
            ${['Pending', 'Under Review', 'Accepted', 'Rejected', 'Revision Required']
				.map(s => `<option ${s === record.status ? 'selected' : ''}>${s}</option>`)
				.join('')}
          </select>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold" for="absNotes">Internal reviewer notes</label>
          <textarea id="absNotes" class="form-control" rows="3" maxlength="1000">${esc(record.notes)}</textarea>
        </div>
      </div>`;
		detailFooter.innerHTML = `
      <button class="btn btn-warning fw-bold" data-save="1">Save Changes</button>
      <button class="btn btn-light border" data-bs-dismiss="modal">Close</button>`;
		detailFooter.onclick = event => {
			if (!event.target.closest('[data-save]')) return;
			record.status = $('absStatusSelect').value;
			record.notes = $('absNotes').value;
			refreshAll();
			detailModal.hide();
			toast('success', 'Abstract updated');
		};
		detailModal.show();
	};

	/* ===== Row actions ===== */
	const confirmDelete = label =>
		Swal.fire({
			icon: 'warning',
			title: 'Delete record?',
			text: `${label} will be permanently removed.`,
			showCancelButton: true,
			confirmButtonText: 'Delete',
			confirmButtonColor: '#DC3545',
		});

	$('regTable').addEventListener('click', async event => {
		const btn = event.target.closest('[data-act]');
		if (!btn) return;
		const record = registrations.find(r => r.id === Number(btn.dataset.id));
		if (!record) return;

		if (btn.dataset.act === 'view') openRegistration(record);
		if (btn.dataset.act === 'confirm') {
			record.status = 'Confirmed';
			refreshAll();
			toast('success', 'Registration confirmed');
		}
		if (btn.dataset.act === 'delete') {
			const result = await confirmDelete(record.ref);
			if (result.isConfirmed) {
				registrations.splice(registrations.indexOf(record), 1);
				refreshAll();
				toast('success', 'Deleted');
			}
		}
	});

	$('absTable').addEventListener('click', async event => {
		const btn = event.target.closest('[data-act]');
		if (!btn) return;
		const record = abstracts.find(a => a.id === Number(btn.dataset.id));
		if (!record) return;

		if (btn.dataset.act === 'view') openAbstract(record);
		if (btn.dataset.act === 'download')
			toast('info', 'Secure download works once the PHP backend is built');
		if (btn.dataset.act === 'delete') {
			const result = await confirmDelete(record.ref);
			if (result.isConfirmed) {
				abstracts.splice(abstracts.indexOf(record), 1);
				refreshAll();
				toast('success', 'Deleted');
			}
		}
	});

	/* ===== CSV buttons (export the currently filtered rows) ===== */
	$('regExport').addEventListener('click', () => {
		const rows = regTable
			.filtered()
			.map(r => [
				r.ref,
				fullName(r),
				r.email,
				r.phone,
				r.country,
				r.org,
				r.category,
				r.type,
				r.date,
				r.status,
			]);
		downloadCsv(
			'registrations.csv',
			[
				'Reference',
				'Name',
				'Email',
				'Phone',
				'Country',
				'Organization',
				'Category',
				'Participation',
				'Date',
				'Status',
			],
			rows
		);
	});
	$('absExport').addEventListener('click', () => {
		const rows = absTable
			.filtered()
			.map(a => [
				a.ref,
				fullName(a),
				a.email,
				a.abstractTitle,
				a.track,
				a.type,
				a.country,
				a.date,
				a.status,
			]);
		downloadCsv(
			'abstracts.csv',
			[
				'Reference',
				'Author',
				'Email',
				'Title',
				'Track',
				'Presentation',
				'Country',
				'Date',
				'Status',
			],
			rows
		);
	});

	/* ===== Settings: hero slide editors ===== */
	const slideDefaults = [
		[
			'Global Science Conference 2027',
			'Theme: Innovation, Collaboration and the Future of Research',
			'Register Now',
			'Submit Abstract',
		],
		[
			'Share Your Research With the World',
			'Present your findings to leading scientists and researchers.',
			'Submit Your Abstract',
			'',
		],
		[
			'Join the Global Conference',
			'Connect with peers and attend keynote sessions.',
			'Register Now',
			'',
		],
	];
	$('slideAccordion').innerHTML = slideDefaults
		.map(
			([heading, text, btn1, btn2], i) => `
    <div class="accordion-item">
      <h3 class="accordion-header">
        <button class="accordion-button ${i ? 'collapsed' : ''}" type="button" data-bs-toggle="collapse" data-bs-target="#slide${i}" aria-expanded="${i ? 'false' : 'true'}">Slide ${i + 1}</button>
      </h3>
      <div id="slide${i}" class="accordion-collapse collapse ${i ? '' : 'show'}" data-bs-parent="#slideAccordion">
        <div class="accordion-body"><div class="row g-3">
          <div class="col-md-6"><label class="form-label fw-semibold" for="slideHead${i}">Heading</label>
            <input type="text" id="slideHead${i}" class="form-control" value="${esc(heading)}"></div>
          <div class="col-md-6"><label class="form-label fw-semibold" for="slideImg${i}">Background image</label>
            <input type="file" id="slideImg${i}" class="form-control" accept="image/png,image/jpeg,image/webp"></div>
          <div class="col-12"><label class="form-label fw-semibold" for="slideText${i}">Description</label>
            <textarea id="slideText${i}" class="form-control" rows="2">${esc(text)}</textarea></div>
          <div class="col-md-6"><label class="form-label fw-semibold" for="slideBtn1${i}">Primary button label</label>
            <input type="text" id="slideBtn1${i}" class="form-control" value="${esc(btn1)}"></div>
          <div class="col-md-6"><label class="form-label fw-semibold" for="slideBtn2${i}">Secondary button label</label>
            <input type="text" id="slideBtn2${i}" class="form-control" value="${esc(btn2)}"></div>
        </div></div>
      </div>
    </div>`
		)
		.join('');

	$('settingsForm').addEventListener('submit', event => {
		event.preventDefault();
		toast('success', 'Settings saved (demo only, database comes with PHP)');
	});

	/* ===== Change password ===== */
	const passwordForm = $('passwordForm');
	passwordForm.addEventListener('submit', event => {
		event.preventDefault();
		const confirmField = $('pwConfirm');
		confirmField.setCustomValidity(
			$('pwNew').value === confirmField.value ? '' : 'Passwords do not match'
		);
		passwordForm.classList.add('was-validated');
		if (!passwordForm.checkValidity()) return;
		toast('success', 'Password form is valid (saving comes with PHP)');
		passwordForm.reset();
		passwordForm.classList.remove('was-validated');
	});

	/* ===== Start ===== */
	renderStats();
	renderRecent();
	regTable.render();
	absTable.render();
	showView(location.hash.replace('#', '') || 'dashboard');
})();

// -----------
