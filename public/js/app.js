/*
 * ============================================================
 * INICIO: CONFIGURACIÓN DE TEMA
 * ============================================================
 */

const THEME_STORAGE_KEY = 'precioCarburanteTheme';

function getResolvedTheme() {
	const manualTheme = document.documentElement.dataset.theme;

	if (manualTheme === 'dark' || manualTheme === 'light') {
		return manualTheme;
	}

	return window.matchMedia('(prefers-color-scheme: dark)').matches ?
			'dark'
		:	'light';
}

function applyTheme(theme) {
	document.documentElement.dataset.theme = theme;

	localStorage.setItem(THEME_STORAGE_KEY, theme);
}

try {
	const storedTheme = localStorage.getItem(THEME_STORAGE_KEY);

	if (storedTheme === 'dark' || storedTheme === 'light') {
		document.documentElement.dataset.theme = storedTheme;
	}
} catch (error) {
	/*
	 * Si localStorage no está disponible,
	 * seguimos con el tema del sistema.
	 */
}

/*
 * ============================================================
 * FIN: CONFIGURACIÓN DE TEMA
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', function () {
	/*
	 * ====================================================
	 * INICIO: SELECTOR DE TEMA
	 * ====================================================
	 */

	const headerContainer = document.querySelector('.site-header .container');

	let themeButton = null;

	if (headerContainer) {
		themeButton = document.createElement('button');

		themeButton.type = 'button';

		themeButton.className = 'theme-toggle';

		const icon = document.createElement('span');

		icon.className = 'theme-toggle-icon';

		icon.setAttribute('aria-hidden', 'true');

		const label = document.createElement('span');

		label.className = 'theme-toggle-label';

		themeButton.appendChild(icon);

		themeButton.appendChild(label);

		function updateThemeButton() {
			const currentTheme = getResolvedTheme();

			if (currentTheme === 'dark') {
				icon.textContent = '☀';

				label.textContent = 'Tema claro';

				themeButton.setAttribute('aria-label', 'Cambiar a tema claro');
			} else {
				icon.textContent = '☾';

				label.textContent = 'Tema oscuro';

				themeButton.setAttribute('aria-label', 'Cambiar a tema oscuro');
			}
		}

		updateThemeButton();

		themeButton.addEventListener('click', function () {
			const currentTheme = getResolvedTheme();

			const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

			applyTheme(newTheme);

			updateThemeButton();

			updateHistoryChartTheme();
		});

		headerContainer.appendChild(themeButton);

		const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

		systemTheme.addEventListener('change', function () {
			const manualTheme = document.documentElement.dataset.theme;

			if (!manualTheme) {
				updateThemeButton();

				updateHistoryChartTheme();
			}
		});
	}

	/*
	 * ====================================================
	 * FIN: SELECTOR DE TEMA
	 * ====================================================
	 */

	/*
	 * ====================================================
	 * INICIO: LISTADO DE MUNICIPIOS
	 * ====================================================
	 */

	const municipalityList = document.querySelector('[data-municipality-list]');

	const municipalityToggle = document.querySelector(
		'[data-municipality-toggle]',
	);

	if (municipalityList && municipalityToggle) {
		const municipalityItems = municipalityList.querySelectorAll(
			'[data-municipality-item]',
		);

		const initialVisible = 18;

		if (municipalityItems.length > initialVisible) {
			municipalityItems.forEach(function (item, index) {
				if (index >= initialVisible) {
					item.hidden = true;
				}
			});

			municipalityToggle.hidden = false;

			let expanded = false;

			municipalityToggle.addEventListener('click', function () {
				expanded = !expanded;

				municipalityItems.forEach(function (item, index) {
					if (index >= initialVisible) {
						item.hidden = !expanded;
					}
				});

				municipalityToggle.setAttribute(
					'aria-expanded',
					expanded ? 'true' : 'false',
				);

				municipalityToggle.textContent =
					expanded ? 'Mostrar menos municipios' : 'Ver todos los municipios';
			});
		}
	}

	/*
	 * ====================================================
	 * FIN: LISTADO DE MUNICIPIOS
	 * ====================================================
	 */

	/*
	 * ====================================================
	 * INICIO: GRÁFICA HISTÓRICA
	 * ====================================================
	 */

	let historyChart = null;
	let homeHistoryChart = null;

	function getChartColors() {
		const styles = getComputedStyle(document.documentElement);

		return {
			text: styles.getPropertyValue('--text').trim(),
			muted: styles.getPropertyValue('--muted').trim(),
			border: styles.getPropertyValue('--border').trim(),
			accent: styles.getPropertyValue('--accent').trim(),
			surface: styles.getPropertyValue('--surface').trim(),
		};
	}

	function updateHistoryChartTheme() {
		const colors = getChartColors();

		if (historyChart) {
			historyChart.options.plugins.legend.labels.color = colors.text;
			historyChart.options.scales.x.ticks.color = colors.muted;
			historyChart.options.scales.y.ticks.color = colors.muted;
			historyChart.options.scales.x.grid.color = colors.border;
			historyChart.options.scales.y.grid.color = colors.border;
			historyChart.data.datasets[0].borderColor = colors.accent;
			historyChart.data.datasets[0].pointBackgroundColor = colors.accent;
			historyChart.data.datasets[0].pointBorderColor = colors.surface;
			historyChart.update();
		}

		if (homeHistoryChart) {
			homeHistoryChart.options.plugins.legend.labels.color = colors.text;
			homeHistoryChart.options.scales.x.ticks.color = colors.muted;
			homeHistoryChart.options.scales.y.ticks.color = colors.muted;
			homeHistoryChart.options.scales.x.grid.color = colors.border;
			homeHistoryChart.options.scales.y.grid.color = colors.border;
			homeHistoryChart.data.datasets[0].borderColor = colors.accent;
			homeHistoryChart.data.datasets[0].pointBackgroundColor = colors.accent;
			homeHistoryChart.data.datasets[0].pointBorderColor = colors.surface;
			homeHistoryChart.update();
		}
	}

	const sectionHeadings = document.querySelectorAll('.site-main section > h2');

	let historySection = null;

	sectionHeadings.forEach(function (heading) {
		if (heading.textContent.trim() === 'Evolución del precio') {
			historySection = heading.closest('section');
		}
	});

	if (historySection) {
		const historyList = historySection.querySelector('.history-list');

		if (historyList) {
			const historyItems = Array.from(
				historyList.querySelectorAll('.history-row'),
			);

			if (historyItems.length >= 2) {
				const labels = [];
				const values = [];

				historyItems.reverse().forEach(function (item) {
					const dateElement = item.querySelector('.history-date time');

					const priceElement = item.querySelector('.history-price strong');

					if (!dateElement || !priceElement) {
						return;
					}

					const rawDate =
						item.dataset.historyDate ||
						dateElement.getAttribute('datetime') ||
						dateElement.textContent.trim();

					const rawPrice =
						item.dataset.historyPrice ||
						priceElement.textContent
							.trim()
							.replace(/\s*€\/l\s*/i, '')
							.replace(/\./g, '')
							.replace(',', '.');

					const price = Number(rawPrice);

					if (!Number.isFinite(price)) {
						return;
					}

					let label = dateElement.textContent.trim();

					if (rawDate) {
						const parsedDate = new Date(String(rawDate).replace(' ', 'T'));

						if (!Number.isNaN(parsedDate.getTime())) {
							label =
								parsedDate.toLocaleDateString('es-ES', {
									day: '2-digit',
									month: '2-digit',
								}) +
								' · ' +
								parsedDate.toLocaleTimeString('es-ES', {
									hour: '2-digit',
									minute: '2-digit',
								});
						}
					}

					labels.push(label);

					values.push(price);
				});

				if (labels.length >= 2 && values.length >= 2) {
					const chartCard = document.createElement('div');

					chartCard.className = 'history-chart-card';

					const chartHeader = document.createElement('div');

					chartHeader.className = 'history-chart-header';

					const chartTitle = document.createElement('strong');

					chartTitle.textContent = 'Evolución histórica';

					const chartSubtitle = document.createElement('span');

					chartSubtitle.textContent = labels.length + ' registros';

					chartHeader.appendChild(chartTitle);

					chartHeader.appendChild(chartSubtitle);

					const chartWrapper = document.createElement('div');

					chartWrapper.className = 'history-chart-wrapper';

					const canvas = document.createElement('canvas');

					canvas.setAttribute('aria-label', 'Gráfico de evolución del precio');

					canvas.setAttribute('role', 'img');

					chartWrapper.appendChild(canvas);

					chartCard.appendChild(chartHeader);

					chartCard.appendChild(chartWrapper);

					historyList.parentNode.insertBefore(chartCard, historyList);

					const chartScript = document.createElement('script');

					chartScript.src =
						'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';

					chartScript.addEventListener('load', function () {
						const colors = getChartColors();

						historyChart = new Chart(canvas, {
							type: 'line',

							data: {
								labels: labels,

								datasets: [
									{
										label: 'Precio €/l',

										data: values,

										borderColor: colors.accent,

										backgroundColor: colors.accent,

										pointBackgroundColor: colors.accent,

										pointBorderColor: colors.surface,

										pointBorderWidth: 2,

										pointRadius: 4,

										pointHoverRadius: 6,

										borderWidth: 3,

										tension: 0.25,

										fill: false,
									},
								],
							},

							options: {
								responsive: true,

								maintainAspectRatio: false,

								interaction: {
									intersect: false,

									mode: 'index',
								},

								plugins: {
									legend: {
										display: false,
									},

									tooltip: {
										callbacks: {
											label: function (context) {
												return (
													context.parsed.y.toLocaleString('es-ES', {
														minimumFractionDigits: 3,

														maximumFractionDigits: 3,
													}) + ' €/l'
												);
											},
										},
									},
								},

								scales: {
									x: {
										grid: {
											display: false,
										},

										ticks: {
											color: colors.muted,

											maxTicksLimit: 8,
										},
									},

									y: {
										beginAtZero: false,

										grid: {
											color: colors.border,
										},

										ticks: {
											color: colors.muted,

											callback: function (value) {
												return (
													Number(value).toLocaleString('es-ES', {
														minimumFractionDigits: 3,

														maximumFractionDigits: 3,
													}) + ' €'
												);
											},
										},
									},
								},
							},
						});
					});

					document.body.appendChild(chartScript);
				}
			}
		}
	}

	/*
	 * ====================================================
	 * FIN: GRÁFICA HISTÓRICA
	 * ====================================================
	 */

	/*
	 * ====================================================
	 * INICIO: GRÁFICA HISTÓRICA HOME
	 * ====================================================
	 */

	const homeHistoryContainer = document.querySelector(
		'[data-home-history-chart]',
	);

	if (homeHistoryContainer) {
		const canvas = homeHistoryContainer.querySelector(
			'[data-home-history-canvas]',
		);

		const historyPoints = Array.from(
			homeHistoryContainer.querySelectorAll('[data-home-history-point]'),
		);

		if (canvas && historyPoints.length >= 2) {
			const labels = [];
			const values = [];

			historyPoints.forEach(function (item) {
				const rawDate = item.dataset.historyDate;
				const rawPrice = item.dataset.historyPrice;

				const price = Number(rawPrice);

				if (!rawDate || !Number.isFinite(price)) {
					return;
				}

				const parsedDate = new Date(String(rawDate).replace(' ', 'T'));

				let label = rawDate;

				if (!Number.isNaN(parsedDate.getTime())) {
					label = parsedDate.toLocaleDateString('es-ES', {
						day: '2-digit',
						month: '2-digit',
						year: 'numeric',
					});
				}

				labels.push(label);
				values.push(price);
			});

			if (labels.length >= 2 && values.length >= 2) {
				const createHomeHistoryChart = function () {
					const colors = getChartColors();

					homeHistoryChart = new Chart(canvas, {
						type: 'line',

						data: {
							labels: labels,

							datasets: [
								{
									label: 'Precio medio €/l',
									data: values,
									borderColor: colors.accent,
									backgroundColor: colors.accent,
									pointBackgroundColor: colors.accent,
									pointBorderColor: colors.surface,
									pointBorderWidth: 2,
									tension: 0.25,
								},
							],
						},

						options: {
							responsive: true,
							maintainAspectRatio: false,

							plugins: {
								legend: {
									labels: {
										color: colors.text,
									},
								},
							},

							scales: {
								x: {
									ticks: {
										color: colors.muted,
									},

									grid: {
										color: colors.border,
									},
								},

								y: {
									ticks: {
										color: colors.muted,
									},

									grid: {
										color: colors.border,
									},
								},
							},
						},
					});
				};

				if (typeof Chart !== 'undefined') {
					createHomeHistoryChart();
				} else {
					const chartScript = document.createElement('script');

					chartScript.src =
						'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';

					chartScript.addEventListener('load', createHomeHistoryChart);

					document.body.appendChild(chartScript);
				}
			}
		}
	}

	/*
	 * ====================================================
	 * FIN: GRÁFICA HISTÓRICA HOME
	 * ====================================================
	 */

	/*
	 * ====================================================
	 * INICIO: AUTOCOMPLETADO DEL BUSCADOR
	 * ====================================================
	 */

	const autocompleteInputs = document.querySelectorAll(
		'[data-search-autocomplete]',
	);

	autocompleteInputs.forEach(function (input) {
		const form = input.closest('.search-form');

		if (!form) {
			return;
		}

		const resultsBox = form.querySelector('[data-search-autocomplete-results]');

		if (!resultsBox) {
			return;
		}

		let timeoutId = null;
		let activeIndex = -1;

		const suggestUrl =
			window.location.pathname.includes('/buscar/') ?
				window.location.pathname.replace(/\/buscar\/.*$/, '/search-suggest.php')
			:	window.location.pathname.replace(/\/$/, '') + '/search-suggest.php';

		function clearResults() {
			resultsBox.innerHTML = '';

			resultsBox.hidden = true;

			activeIndex = -1;
		}

		function updateActiveItem() {
			const items = resultsBox.querySelectorAll('.search-autocomplete-item');

			items.forEach(function (item, index) {
				item.classList.toggle('is-active', index === activeIndex);
			});
		}

		function renderResults(results) {
			resultsBox.innerHTML = '';

			activeIndex = -1;

			if (!Array.isArray(results) || results.length === 0) {
				resultsBox.hidden = true;

				return;
			}

			results.forEach(function (result) {
				const link = document.createElement('a');

				link.className = 'search-autocomplete-item';

				const basePath =
					document
						.querySelector('.brand')
						?.getAttribute('href')
						?.replace(/\/$/, '') || '';

				link.href = basePath + result.url;

				const main = document.createElement('span');

				main.className = 'search-autocomplete-main';

				const label = document.createElement('strong');

				label.textContent = result.label;

				const meta = document.createElement('small');

				meta.textContent = result.meta;

				main.appendChild(label);

				main.appendChild(meta);

				const arrow = document.createElement('span');

				arrow.className = 'search-autocomplete-arrow';

				arrow.setAttribute('aria-hidden', 'true');

				arrow.textContent = '→';

				link.appendChild(main);

				link.appendChild(arrow);

				resultsBox.appendChild(link);
			});

			resultsBox.hidden = false;
		}

		async function fetchSuggestions(value) {
			try {
				const response = await fetch(
					suggestUrl + '?q=' + encodeURIComponent(value),
					{
						headers: {
							Accept: 'application/json',
						},
					},
				);

				if (!response.ok) {
					clearResults();
					return;
				}

				const data = await response.json();

				renderResults(data.results || []);
			} catch (error) {
				clearResults();
			}
		}

		input.addEventListener('input', function () {
			const value = input.value.trim();

			clearTimeout(timeoutId);

			if (value.length < 2) {
				clearResults();

				return;
			}

			timeoutId = setTimeout(function () {
				fetchSuggestions(value);
			}, 180);
		});

		input.addEventListener('keydown', function (event) {
			const items = resultsBox.querySelectorAll('.search-autocomplete-item');

			if (resultsBox.hidden || items.length === 0) {
				return;
			}

			if (event.key === 'ArrowDown') {
				event.preventDefault();

				activeIndex = Math.min(activeIndex + 1, items.length - 1);

				updateActiveItem();
			}

			if (event.key === 'ArrowUp') {
				event.preventDefault();

				activeIndex = Math.max(activeIndex - 1, 0);

				updateActiveItem();
			}

			if (event.key === 'Enter' && activeIndex >= 0) {
				event.preventDefault();

				items[activeIndex].click();
			}

			if (event.key === 'Escape') {
				clearResults();
			}
		});

		document.addEventListener('click', function (event) {
			if (!form.contains(event.target)) {
				clearResults();
			}
		});
	});

	/*
	 * ====================================================
	 * FIN: AUTOCOMPLETADO DEL BUSCADOR
	 * ====================================================
	 */

	/*
	 * ====================================================
	 * INICIO: MAPA LEAFLET
	 * ====================================================
	 */

	const mapElement = document.getElementById('station-map');

	if (!mapElement) {
		return;
	}

	const latitude = Number(mapElement.dataset.latitude);

	const longitude = Number(mapElement.dataset.longitude);

	const stationName = mapElement.dataset.name || 'Estación de servicio';

	const stationAddress = mapElement.dataset.address || '';

	if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
		return;
	}

	const leafletCss = document.createElement('link');

	leafletCss.rel = 'stylesheet';

	leafletCss.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';

	leafletCss.crossOrigin = '';

	document.head.appendChild(leafletCss);

	const leafletScript = document.createElement('script');

	leafletScript.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

	leafletScript.crossOrigin = '';

	leafletScript.addEventListener('load', function () {
		const map = L.map(mapElement, {
			scrollWheelZoom: false,
		}).setView([latitude, longitude], 16);

		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,

			attribution:
				'&copy; ' +
				'<a href="https://www.openstreetmap.org/copyright">' +
				'OpenStreetMap' +
				'</a> contributors',
		}).addTo(map);

		const marker = L.marker([latitude, longitude]).addTo(map);

		const popup = document.createElement('div');

		const popupTitle = document.createElement('strong');

		popupTitle.textContent = stationName;

		popup.appendChild(popupTitle);

		if (stationAddress !== '') {
			const popupAddress = document.createElement('div');

			popupAddress.textContent = stationAddress;

			popup.appendChild(popupAddress);
		}

		marker.bindPopup(popup);

		marker.openPopup();
	});

	document.body.appendChild(leafletScript);

	/*
	 * ====================================================
	 * FIN: MAPA LEAFLET
	 * ====================================================
	 */
});
