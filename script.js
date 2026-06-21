(function () {
    'use strict';

    /* ───── 1. NAVIGATION ───── */
    function initNavigation() {
        var navbar = document.getElementById('navbar');
        var toggle = document.getElementById('mobileToggle');
        var navLinks = document.querySelector('.nav-links');
        var links = document.querySelectorAll('.nav-link');
        var ticking = false;

        /* Scroll-activated glassmorphism */
        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(function () {
                    if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 40);
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });

        /* Mobile toggle with slide-in */
        if (toggle && navLinks) {
            toggle.addEventListener('click', function () {
                var isOpen = navLinks.classList.contains('open');
                toggle.classList.toggle('active');
                toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                toggle.setAttribute('aria-label', isOpen ? 'Menü öffnen' : 'Menü schließen');
                navLinks.classList.toggle('open');

                /* Prevent body scroll when menu is open */
                document.body.style.overflow = isOpen ? '' : 'hidden';
            });

            /* Close on link click */
            links.forEach(function (l) {
                l.addEventListener('click', function () {
                    toggle.classList.remove('active');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Menü öffnen');
                    navLinks.classList.remove('open');
                    document.body.style.overflow = '';
                });
            });

            /* Close on outside click */
            document.addEventListener('click', function (e) {
                if (navLinks.classList.contains('open') &&
                    !navLinks.contains(e.target) &&
                    !toggle.contains(e.target)) {
                    toggle.classList.remove('active');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Menü öffnen');
                    navLinks.classList.remove('open');
                    document.body.style.overflow = '';
                }
            });

            /* Close on Escape key */
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && navLinks.classList.contains('open')) {
                    toggle.classList.remove('active');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Menü öffnen');
                    navLinks.classList.remove('open');
                    document.body.style.overflow = '';
                    toggle.focus();
                }
            });
        }
    }

    /* ───── 2. SCROLL REVEAL ───── */
    function initScrollReveal() {
        var cards = document.querySelectorAll('.glass-card');
        if (!cards.length) return;

        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        cards.forEach(function (c) { obs.observe(c); });
    }

    /* ───── 3. CONTACT FORM ───── */
    function initContactForm() {
        var form = document.getElementById('contactForm');
        if (!form) return;

        var feedback = document.getElementById('contactFeedback');
        var csrfInput = document.getElementById('contactCsrfToken');
        var submitBtn = form.querySelector('.btn-primary');

        function setFeedback(msg, isError) {
            if (!feedback) return;
            feedback.textContent = msg;
            feedback.classList.remove('is-error', 'is-success');
            feedback.classList.add(isError ? 'is-error' : 'is-success');
        }

        function clearFeedback() {
            if (!feedback) return;
            feedback.textContent = '';
            feedback.classList.remove('is-error', 'is-success');
        }

        function fetchContactCsrf() {
            fetch('handler/csrf-token.php?form=contact', { cache: 'no-store', credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (csrfInput && data.token) {
                        csrfInput.value = data.token;
                    }
                })
                .catch(function () { /* Token wird serverseitig abgelehnt */ });
        }
        fetchContactCsrf();

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearFeedback();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.innerHTML = '<span>Wird gesendet...</span>';
                submitBtn.style.pointerEvents = 'none';
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'handler/process-contact.php', true);

            xhr.addEventListener('load', function () {
                var result;
                try {
                    result = JSON.parse(xhr.responseText || '{}');
                } catch (_e) {
                    result = { success: false, message: 'Unerwartete Serverantwort.' };
                }

                if (xhr.status >= 200 && xhr.status < 300 && result.success) {
                    setFeedback('Nachricht erfolgreich gesendet! Wir melden uns bei Ihnen.', false);
                    if (submitBtn) {
                        submitBtn.innerHTML = '<span>Gesendet ✓</span>';
                        submitBtn.style.background = 'linear-gradient(135deg,#34d399,#10b981)';
                    }
                    setTimeout(function () {
                        form.reset();
                        if (submitBtn) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.style.background = '';
                            submitBtn.style.pointerEvents = '';
                        }
                        clearFeedback();
                        fetchContactCsrf();
                    }, 3000);
                    return;
                }

                setFeedback(result.message || 'Nachricht konnte nicht gesendet werden.', true);
                fetchContactCsrf();
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.style.pointerEvents = '';
                }
            });

            xhr.addEventListener('error', function () {
                setFeedback('Verbindung fehlgeschlagen. Bitte später erneut versuchen.', true);
                fetchContactCsrf();
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.style.pointerEvents = '';
                }
            });

            xhr.send(new FormData(form));
        });
    }

    /* ───── 4. CAREER PAGE ───── */
    function initCareerPage() {
        var list = document.getElementById('jobsList');
        var form = document.getElementById('applicationForm');
        if (!list || !form) return;

        var select = document.getElementById('jobSelect');
        var feedback = document.getElementById('applicationFeedback');
        var cvInput = document.getElementById('applicationCv');
        var submitBtn = form.querySelector('.btn-primary');
        var fileDropArea = document.getElementById('fileDropArea');
        var fileProgressBar = document.getElementById('fileProgressBar');
        var fileProgressText = document.getElementById('fileProgressText');
        var fileChip = document.getElementById('fileChip');
        var fileName = document.getElementById('fileName');
        var fileIcon = document.getElementById('fileIcon');
        var fileRemoveBtn = document.getElementById('fileRemoveBtn');
        var csrfInput = document.getElementById('csrfToken');
        var maxSizeBytes = 5 * 1024 * 1024;
        var readToken = 0;
        var uploadFallbackTimer = null;

        /* ── CSRF-Token laden ── */
        function fetchCsrfToken() {
            fetch('handler/csrf-token.php', { cache: 'no-store', credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (csrfInput && data.token) {
                        csrfInput.value = data.token;
                    }
                })
                .catch(function () {
                    /* Token konnte nicht geladen werden – Formular wird serverseitig abgelehnt */
                });
        }
        fetchCsrfToken();

        function setFeedback(message, isError) {
            if (!feedback) return;
            feedback.textContent = message;
            feedback.classList.remove('is-error', 'is-success');
            feedback.classList.add(isError ? 'is-error' : 'is-success');
        }

        function clearFeedback() {
            if (!feedback) return;
            feedback.textContent = '';
            feedback.classList.remove('is-error', 'is-success');
        }

        function setProgress(value) {
            var safeValue = Math.max(0, Math.min(100, value));
            if (fileProgressBar) {
                fileProgressBar.style.width = safeValue + '%';
            }
            if (fileProgressText) {
                fileProgressText.textContent = safeValue + '%';
            }
            var wrap = document.getElementById('fileProgressWrap');
            if (wrap) {
                wrap.style.display = (safeValue > 0 && safeValue < 100) ? 'flex' : 'none';
            }
        }

        function stopUploadFallback() {
            if (uploadFallbackTimer) {
                clearInterval(uploadFallbackTimer);
                uploadFallbackTimer = null;
            }
        }

        function startUploadFallback() {
            stopUploadFallback();
            var progress = 10;
            setProgress(progress);
            uploadFallbackTimer = setInterval(function () {
                progress = Math.min(90, progress + 3);
                setProgress(progress);
            }, 140);
        }

        function resolveFileKind(file) {
            if (!file) return 'file';
            var type = String(file.type || '').toLowerCase();
            var name = String(file.name || '').toLowerCase();

            if (type === 'application/pdf' || /\.pdf$/.test(name)) {
                return 'pdf';
            }
            if (type === 'image/png' || /\.png$/.test(name)) {
                return 'png';
            }
            if (type === 'image/jpeg' || /\.(jpg|jpeg)$/.test(name)) {
                return 'img';
            }
            return 'file';
        }

        function renderFileChip(file) {
            if (!fileChip || !fileName || !fileIcon) return;
            fileName.textContent = file ? file.name : '';
            fileIcon.classList.remove('is-pdf', 'is-png', 'is-img', 'is-file');

            var kind = resolveFileKind(file);
            if (kind === 'pdf') {
                fileIcon.classList.add('is-pdf');
                fileIcon.textContent = 'PDF';
            } else if (kind === 'png') {
                fileIcon.classList.add('is-png');
                fileIcon.textContent = 'PNG';
            } else if (kind === 'img') {
                fileIcon.classList.add('is-img');
                fileIcon.textContent = 'IMG';
            } else {
                fileIcon.classList.add('is-file');
                fileIcon.textContent = 'FILE';
            }

            fileChip.hidden = !file;
            var fileDropArea = document.getElementById('fileDropArea');
            if (fileDropArea) {
                fileDropArea.style.display = file ? 'none' : 'flex';
            }
        }

        function clearSelectedFile() {
            readToken += 1;
            if (cvInput) cvInput.value = '';
            renderFileChip(null);
            setProgress(0);
        }

        function readSelectedFile(file, token) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();

                reader.onprogress = function (event) {
                    if (token !== readToken) return;
                    if (!event.lengthComputable) return;
                    var percent = Math.round((event.loaded / event.total) * 100);
                    setProgress(percent);
                };

                reader.onload = function () {
                    if (token !== readToken) return;
                    setProgress(100);
                    resolve();
                };

                reader.onerror = function () {
                    if (token !== readToken) return;
                    reject(new Error('Datei konnte nicht gelesen werden.'));
                };

                reader.readAsArrayBuffer(file);
            });
        }

        function getPreselectedJobId() {
            var params = new URLSearchParams(window.location.search);
            return params.get('job') || '';
        }

        function renderJobs(jobs) {
            list.innerHTML = jobs.map(function (job) {
                var employmentTypes = Array.isArray(job.employmentTypes) ? job.employmentTypes.join(', ') : (job.type || '-');
                var detailId = 'job-detail-' + createSafeId(job.id || job.title || 'job');

                return [
                    '<article class="glass-card job-card">',
                    '  <div class="card-shine"></div>',
                    '  <div class="job-card-content">',
                    '    <button type="button" class="job-toggle" data-toggle-id="' + escapeHtml(detailId) + '" aria-expanded="false" aria-controls="' + escapeHtml(detailId) + '">',
                    '      <span class="job-toggle-top">',
                    '        <span class="job-ref">Referenz: ' + escapeHtml(job.id) + '</span>',
                    '        <span class="job-toggle-icon" aria-hidden="true">+</span>',
                    '      </span>',
                    '      <span class="job-toggle-title">' + escapeHtml(job.title) + '</span>',
                    '      <span class="job-meta">',
                    '        <span>' + escapeHtml(job.location) + '</span>',
                    '        <span>' + escapeHtml(job.workdays || '-') + '</span>',
                    '        <span>' + escapeHtml(job.startDate || '-') + '</span>',
                    '      </span>',
                    '      <span class="job-types"><strong>Anstellungsart:</strong> ' + escapeHtml(employmentTypes) + '</span>',
                    '    </button>',
                    '    <div class="job-details" id="' + escapeHtml(detailId) + '">',
                    '      <p class="job-description">' + escapeHtml(job.description) + '</p>',
                    renderJobList('Aufgaben', job.tasks),
                    renderJobList('Was bringst du mit', job.requirements),
                    renderJobList('Wir bieten', job.benefits),
                    '      <button type="button" class="btn btn-primary apply-btn" data-job-id="' + escapeHtml(job.id) + '">Jetzt bewerben</button>',
                    '    </div>',
                    '  </div>',
                    '</article>'
                ].join('');
            }).join('');

            list.querySelectorAll('.glass-card').forEach(function (card) {
                card.classList.add('visible');
            });
        }

        function fillSelect(jobs) {
            if (!select) return;
            var options = ['<option value="" disabled selected>Bitte Stelle wählen</option>'];
            jobs.forEach(function (job) {
                options.push('<option value="' + escapeHtml(job.id) + '">' + escapeHtml(job.title) + '</option>');
            });
            select.innerHTML = options.join('');
        }

        function renderJobList(label, items) {
            if (!Array.isArray(items) || !items.length) {
                return '';
            }

            var li = items.map(function (item) {
                return '<li>' + escapeHtml(item) + '</li>';
            }).join('');

            return [
                '<div class="job-detail-block">',
                '  <h4>' + escapeHtml(label) + '</h4>',
                '  <ul class="job-list">' + li + '</ul>',
                '</div>'
            ].join('');
        }

        function setSelectedJob(jobId) {
            if (!select || !jobId) return;
            select.value = jobId;
            var section = document.getElementById('bewerben');
            if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        list.addEventListener('click', function (event) {
            var toggle = event.target.closest('.job-toggle');
            if (toggle) {
                var card = toggle.closest('.job-card');
                if (!card) return;

                var isOpen = card.classList.contains('is-open');
                list.querySelectorAll('.job-card.is-open').forEach(function (openCard) {
                    openCard.classList.remove('is-open');
                    var btn = openCard.querySelector('.job-toggle');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                });

                if (!isOpen) {
                    card.classList.add('is-open');
                    toggle.setAttribute('aria-expanded', 'true');
                }
                return;
            }

            var button = event.target.closest('.apply-btn');
            if (!button) return;
            setSelectedJob(button.getAttribute('data-job-id'));
        });

        if (cvInput) {
            cvInput.addEventListener('change', function () {
                clearFeedback();
                readToken += 1;
                var currentToken = readToken;
                var file = cvInput.files && cvInput.files[0];
                if (!file) {
                    clearSelectedFile();
                    return;
                }
                if (file.size > maxSizeBytes) {
                    clearSelectedFile();
                    setFeedback('Die Datei ist zu groß. Maximal 5 MB sind erlaubt.', true);
                    return;
                }
                renderFileChip(file);
                readSelectedFile(file, currentToken).catch(function () {
                    if (currentToken !== readToken) return;
                    clearSelectedFile();
                    setFeedback('Die Datei konnte nicht verarbeitet werden. Bitte erneut wählen.', true);
                });
            });
        }

        if (fileDropArea && cvInput) {
            fileDropArea.addEventListener('click', function () {
                cvInput.click();
            });
            fileDropArea.addEventListener('dragover', function (e) {
                e.preventDefault();
                fileDropArea.classList.add('dragover');
            });
            fileDropArea.addEventListener('dragleave', function (e) {
                e.preventDefault();
                fileDropArea.classList.remove('dragover');
            });
            fileDropArea.addEventListener('drop', function (e) {
                e.preventDefault();
                fileDropArea.classList.remove('dragover');
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    cvInput.files = e.dataTransfer.files;
                    cvInput.dispatchEvent(new Event('change'));
                }
            });
        }

        if (fileRemoveBtn) {
            fileRemoveBtn.addEventListener('click', function () {
                clearFeedback();
                clearSelectedFile();
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearFeedback();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (cvInput && cvInput.files && cvInput.files[0] && cvInput.files[0].size > maxSizeBytes) {
                setFeedback('Die Datei ist zu groß. Maximal 5 MB sind erlaubt.', true);
                return;
            }

            var originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.innerHTML = '<span>Wird gesendet...</span>';
                submitBtn.style.pointerEvents = 'none';
            }

            startUploadFallback();

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'handler/process-application.php', true);

            xhr.upload.addEventListener('progress', function (event) {
                if (!event.lengthComputable) return;
                stopUploadFallback();
                var percent = Math.round((event.loaded / event.total) * 100);
                setProgress(percent);
            });

            xhr.addEventListener('load', function () {
                var result;
                try {
                    result = JSON.parse(xhr.responseText || '{}');
                } catch (_e) {
                    result = { success: false, message: 'Unerwartete Serverantwort.' };
                }

                if (xhr.status >= 200 && xhr.status < 300 && result.success) {
                    stopUploadFallback();
                    setProgress(100);
                    window.location.href = 'danke.html';
                    return;
                }

                stopUploadFallback();
                setFeedback(result.message || 'Bewerbung konnte nicht gesendet werden.', true);
                setProgress(0);
                fetchCsrfToken();
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.style.pointerEvents = '';
                }
            });

            xhr.addEventListener('error', function () {
                stopUploadFallback();
                setFeedback('Verbindung fehlgeschlagen. Bitte später erneut versuchen.', true);
                setProgress(0);
                fetchCsrfToken();
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.style.pointerEvents = '';
                }
            });

            xhr.send(new FormData(form));
        });

        fetch('jobs.php', { cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) throw new Error('Stellenangebote konnten nicht geladen werden.');
                return response.json();
            })
            .then(function (jobs) {
                if (!Array.isArray(jobs) || !jobs.length) {
                    list.innerHTML = '<p class="jobs-empty">Aktuell sind keine Stellen ausgeschrieben. Bitte schauen Sie bald wieder vorbei.</p>';
                    return;
                }
                renderJobs(jobs);
                fillSelect(jobs);
                setSelectedJob(getPreselectedJobId());
            })
            .catch(function () {
                list.innerHTML = '<p class="jobs-empty">Die Stellen konnten derzeit nicht geladen werden. Bitte später erneut versuchen.</p>';
            });
    }

    /* ───── 5. SMOOTH SCROLL ───── */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var targetAttr = this.getAttribute('href');
                if (targetAttr === "#") return;
                var t = document.querySelector(targetAttr);
                if (!t) return;
                e.preventDefault();
                t.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    /* ───── 6. SERVICE SLIDER ───── */
    function initSlider() {
        var track = document.getElementById('slideTrack');
        var tabs = document.querySelectorAll('.slide-tab');
        var nav = document.getElementById('slideNav');
        var arrowL = document.getElementById('navArrowLeft');
        var arrowR = document.getElementById('navArrowRight');
        if (!track || !tabs.length) return;

        var current = 0;
        var total = track.children.length;
        var autoTimer;
        var touchStartX = 0;
        var touchStartY = 0;

        function goTo(index) {
            if (index < 0) index = total - 1;
            if (index >= total) index = 0;
            current = index;
            track.style.transform = 'translateX(-' + (current * 100) + '%)';
            tabs.forEach(function (t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            tabs[current].classList.add('active');
            tabs[current].setAttribute('aria-selected', 'true');
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                goTo(parseInt(tab.dataset.slide));
                resetAuto();
            });
        });

        function startAuto() {
            autoTimer = setInterval(function () {
                goTo(current + 1);
            }, 5000);
        }

        function resetAuto() {
            clearInterval(autoTimer);
            startAuto();
        }

        if (arrowL && arrowR && nav) {
            arrowL.addEventListener('click', function () {
                nav.scrollBy({ left: -150, behavior: 'smooth' });
            });
            arrowR.addEventListener('click', function () {
                nav.scrollBy({ left: 150, behavior: 'smooth' });
            });
        }

        track.addEventListener('touchstart', function (event) {
            if (!event.touches || !event.touches.length) return;
            touchStartX = event.touches[0].clientX;
            touchStartY = event.touches[0].clientY;
        }, { passive: true });

        track.addEventListener('touchmove', function (event) {
            if (!event.touches || !event.touches.length) return;
            var deltaX = event.touches[0].clientX - touchStartX;
            var deltaY = event.touches[0].clientY - touchStartY;
            if (Math.abs(deltaX) > Math.abs(deltaY)) {
                event.preventDefault();
            }
        }, { passive: false });

        track.addEventListener('touchend', function (event) {
            if (!event.changedTouches || !event.changedTouches.length) return;

            var diffX = event.changedTouches[0].clientX - touchStartX;
            var diffY = event.changedTouches[0].clientY - touchStartY;
            var threshold = 50;

            if (Math.abs(diffX) > threshold && Math.abs(diffX) > Math.abs(diffY)) {
                goTo(diffX < 0 ? current + 1 : current - 1);
                resetAuto();
            }
        }, { passive: true });

        startAuto();
    }

    /* ───── 7. SPOTLIGHT EFFECT ───── */
    function initSpotlight() {
        var cards = document.querySelectorAll('.glass-card');
        if (!cards.length) return;

        cards.forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                var rect = card.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                card.style.setProperty('--mouse-x', x + 'px');
                card.style.setProperty('--mouse-y', y + 'px');
            });
        });
    }

    /* ───── 8. HERO PARALLAX ───── */
    function initHeroParallax() {
        var heroContainer = document.querySelector('.hero-container');
        if (!heroContainer) return;

        var ticking = false;
        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(function () {
                    var scrollY = window.scrollY;
                    if (scrollY <= 600) {
                        var opacity = Math.max(0, 1 - (scrollY / 450));
                        var scale = Math.max(0.94, 1 - (scrollY / 8000));
                        var y = scrollY * 0.12;

                        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                            heroContainer.style.opacity = opacity;
                            heroContainer.style.transform = 'translate3d(0, ' + y + 'px, 0) scale(' + scale + ')';
                        } else {
                            heroContainer.style.opacity = '';
                            heroContainer.style.transform = '';
                        }
                    }
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    }

    /* ───── BOOT ───── */
    function boot() {
        initNavigation();
        initScrollReveal();
        initContactForm();
        initCareerPage();
        initSmoothScroll();
        initSlider();
        initSpotlight();
        initHeroParallax();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function createSafeId(value) {
        return String(value)
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
