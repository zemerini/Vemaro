(function () {
    'use strict';



    /* ───── 2. NAVIGATION ───── */
    function initNavigation() {
        var navbar = document.getElementById('navbar');
        var toggle = document.getElementById('mobileToggle');
        var navLinks = document.querySelector('.nav-links');
        var links = document.querySelectorAll('.nav-link');
        var ticking = false;

        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(function () {
                    if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 40);
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });

        if (toggle && navLinks) {
            toggle.addEventListener('click', function () {
                toggle.classList.toggle('active');
                navLinks.classList.toggle('open');
            });
            links.forEach(function (l) {
                l.addEventListener('click', function () {
                    toggle.classList.remove('active');
                    navLinks.classList.remove('open');
                });
            });
        }
    }

    /* ───── 3. SCROLL REVEAL ───── */
    function initScrollReveal() {
        var cards = document.querySelectorAll('.glass-card');
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
            });
        }, { threshold: 0.15 });
        cards.forEach(function (c) { obs.observe(c); });
    }

    /* ───── 4. CONTACT FORM ───── */
    function initContactForm() {
        var form = document.getElementById('contactForm');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('.btn-primary');
            var orig = btn.innerHTML;
            btn.innerHTML = '<span>Gesendet ✓</span>';
            btn.style.background = 'linear-gradient(135deg,#10b981,#059669)';
            btn.style.pointerEvents = 'none';
            setTimeout(function () {
                btn.innerHTML = orig;
                btn.style.background = '';
                btn.style.pointerEvents = '';
                form.reset();
            }, 2500);
        });
    }

    /* ───── 5. CAREER PAGE ───── */
    function initCareerPage() {
        var list = document.getElementById('jobsList');
        var form = document.getElementById('applicationForm');
        if (!list || !form) return;

        var select = document.getElementById('jobSelect');
        var feedback = document.getElementById('applicationFeedback');
        var cvInput = document.getElementById('applicationCv');
        var submitBtn = form.querySelector('.btn-primary');
        var fileSelectBtn = document.getElementById('fileSelectBtn');
        var fileProgressBar = document.getElementById('fileProgressBar');
        var fileProgressText = document.getElementById('fileProgressText');
        var fileChip = document.getElementById('fileChip');
        var fileName = document.getElementById('fileName');
        var fileIcon = document.getElementById('fileIcon');
        var fileRemoveBtn = document.getElementById('fileRemoveBtn');
        var maxSizeBytes = 5 * 1024 * 1024;
        var readToken = 0;
        var uploadFallbackTimer = null;

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

        if (fileSelectBtn && cvInput) {
            fileSelectBtn.addEventListener('click', function () {
                cvInput.click();
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
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.style.pointerEvents = '';
                }
            });

            xhr.addEventListener('error', function () {
                stopUploadFallback();
                setFeedback('Verbindung fehlgeschlagen. Bitte später erneut versuchen.', true);
                setProgress(0);
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.style.pointerEvents = '';
                }
            });

            xhr.send(new FormData(form));
        });

        fetch('jobs.json', { cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) throw new Error('jobs.json konnte nicht geladen werden.');
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

    /* ───── 6. SMOOTH SCROLL ───── */
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

    /* ───── 7. SERVICE SLIDER ───── */
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

        function goTo(index) {
            if (index < 0) index = total - 1;
            if (index >= total) index = 0;
            current = index;
            track.style.transform = 'translateX(-' + (current * 100) + '%)';
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tabs[current].classList.add('active');
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

        startAuto();
    }

    /* ───── BOOT ───── */
    function boot() {
        initNavigation();
        initScrollReveal();
        initContactForm();
        initCareerPage();
        initSmoothScroll();
        initSlider();
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
