/**
 * Umoja Voices (uv-wp) Client-Side Interactivity
 */
(function () {
  "use strict";

  async function postAction(formData) {
    formData.set("uv_json", "1");
    const resp = await fetch(window.location.pathname, {
      method: "POST",
      headers: { Accept: "application/json" },
      body: formData,
    });
    return resp.json();
  }

  // ---------------------------------------------------------------------------
  // 1. Mobile Navigation Drawer & Admin Dropdown
  // ---------------------------------------------------------------------------
  const mobileBtn = document.getElementById("uv-mobile-menu-toggle");
  const mobileDrawer = document.getElementById("uv-mobile-drawer");
  if (mobileBtn && mobileDrawer) {
    mobileBtn.addEventListener("click", function () {
      const isHidden = mobileDrawer.classList.contains("hidden");
      mobileDrawer.classList.toggle("hidden", !isHidden);
      mobileBtn.innerHTML = isHidden ? "&#10005;" : "&#9776;";
    });
  }

  const adminBtn = document.getElementById("uv-admin-dropdown-btn");
  const adminMenu = document.getElementById("uv-admin-dropdown-menu");
  const adminWrap = document.getElementById("uv-admin-dropdown-wrap");
  if (adminBtn && adminMenu && adminWrap) {
    adminBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      adminMenu.classList.toggle("hidden");
    });
    document.addEventListener("mousedown", function (e) {
      if (!adminWrap.contains(e.target)) {
        adminMenu.classList.add("hidden");
      }
    });
  }

  // ---------------------------------------------------------------------------
  // 2. 7-Layer Anti-Spam Human Interaction Token
  // ---------------------------------------------------------------------------
  function markHumanInteraction() {
    document.querySelectorAll(".uv-human-token-input").forEach(function (el) {
      if (!el.value) {
        el.value = "human-" + Date.now();
      }
    });
  }
  ["focusin", "keydown", "pointerdown", "touchstart"].forEach(function (evt) {
    document.addEventListener(evt, markHumanInteraction, { once: true, passive: true });
  });

  // ---------------------------------------------------------------------------
  // 3. Expandable ("See more" / "See less")
  // ---------------------------------------------------------------------------
  document.querySelectorAll(".uv-expandable").forEach(function (wrap) {
    const collapsedHeight = parseInt(wrap.getAttribute("data-collapsed-height") || "160", 10);
    const content = wrap.querySelector(".uv-expandable-content");
    const fade = wrap.querySelector(".uv-expandable-fade");
    const btn = wrap.querySelector(".uv-expandable-btn");
    if (!content || !btn) return;

    let expanded = false;
    function updateExpandable() {
      const overflowing = content.scrollHeight > collapsedHeight;
      if (!overflowing) {
        content.style.maxHeight = "";
        content.style.overflow = "";
        if (fade) fade.classList.add("hidden");
        btn.classList.add("hidden");
        return;
      }
      btn.classList.remove("hidden");
      if (expanded) {
        content.style.maxHeight = "";
        content.style.overflow = "";
        if (fade) fade.classList.add("hidden");
        btn.textContent = "See less";
      } else {
        content.style.maxHeight = collapsedHeight + "px";
        content.style.overflow = "hidden";
        if (fade) fade.classList.remove("hidden");
        btn.textContent = "See more";
      }
    }
    btn.addEventListener("click", function () {
      expanded = !expanded;
      updateExpandable();
    });
    updateExpandable();
  });

  // ---------------------------------------------------------------------------
  // 4. Auth Set-Password Forms (/accept-invite, /reset-password)
  // ---------------------------------------------------------------------------
  const setPwForm = document.getElementById("uv-setpw-form");
  if (setPwForm) {
    setPwForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const errEl = document.getElementById("uv-setpw-error");
      if (errEl) errEl.classList.add("hidden");
      const fd = new FormData(setPwForm);
      fd.set("uv_action", "complete_password_set");
      try {
        const res = await postAction(fd);
        if (res.error) {
          if (errEl) {
            errEl.textContent = res.error;
            errEl.classList.remove("hidden");
          }
          return;
        }
        const doneEl = document.getElementById("uv-setpw-done");
        const wrapEl = document.getElementById("uv-setpw-wrap");
        if (wrapEl) wrapEl.classList.add("hidden");
        if (doneEl) doneEl.classList.remove("hidden");
        setTimeout(function () {
          window.location.href = "/login";
        }, 1500);
      } catch (err) {
        if (errEl) {
          errEl.textContent = "Something went wrong. Please try again.";
          errEl.classList.remove("hidden");
        }
      }
    });
  }

  // ---------------------------------------------------------------------------
  // 5. Song Detail: Delete Song Button
  // ---------------------------------------------------------------------------
  document.querySelectorAll(".uv-delete-song-btn").forEach(function (btn) {
    btn.addEventListener("click", async function () {
      const songId = btn.getAttribute("data-song-id");
      const title = btn.getAttribute("data-song-title") || "this song";
      if (!window.confirm('Delete "' + title + '"? This cannot be undone.')) return;
      const fd = new FormData();
      fd.set("uv_action", "delete_song");
      fd.set("songId", songId);
      const res = await postAction(fd);
      if (res && res.ok) {
        window.location.href = "/songs";
      }
    });
  });

  // ---------------------------------------------------------------------------
  // 6. Media Page: SATB Filter, Single-Active-Audio Pause, Play All, Loop, Add/Remove
  // ---------------------------------------------------------------------------
  const mediaContainer = document.getElementById("uv-media-groups-container");
  if (mediaContainer) {
    let playAll = false;
    let loop = false;
    let selectedPart = "ALL";

    const playAllBtn = document.getElementById("uv-play-all-btn");
    const loopBtn = document.getElementById("uv-loop-btn");
    const filterBtns = mediaContainer.querySelectorAll(".uv-media-filter-btn");
    const groupSections = mediaContainer.querySelectorAll(".uv-media-group-section");
    const emptyMsg = document.getElementById("uv-media-empty-msg");

    function setPillStyle(btn, active) {
      if (active) {
        btn.className = btn.className
          .replace("border-ink/20", "border-ink")
          .replace("bg-white", "bg-ink")
          .replace("text-ink", "text-white");
        if (!btn.className.includes("bg-ink")) {
          btn.classList.add("border-ink", "bg-ink", "text-white");
        }
      } else {
        btn.classList.remove("border-ink", "bg-ink", "text-white");
        btn.classList.add("border-ink/20", "bg-white", "text-ink");
      }
    }

    function getVisiblePlayables() {
      const list = [];
      groupSections.forEach(function (sec) {
        if (!sec.classList.contains("hidden")) {
          sec.querySelectorAll(".uv-playable-media").forEach(function (el) {
            list.push(el);
          });
        }
      });
      return list;
    }

    function syncLoopAttributes() {
      const nativeLoop = !playAll && loop;
      mediaContainer.querySelectorAll(".uv-playable-media").forEach(function (el) {
        el.loop = nativeLoop;
      });
    }

    // Pause every other audio/video when one starts playing
    const allPlayables = Array.from(mediaContainer.querySelectorAll(".uv-playable-media"));
    allPlayables.forEach(function (el) {
      el.addEventListener("play", function () {
        allPlayables.forEach(function (other) {
          if (other !== el && !other.paused) {
            other.pause();
          }
        });
      });

      el.addEventListener("ended", function () {
        if (!playAll) return;
        const visible = getVisiblePlayables();
        const idx = visible.indexOf(el);
        if (idx === -1) return;
        if (idx + 1 < visible.length) {
          const next = visible[idx + 1];
          try {
            el.pause();
          } catch (_) {}
          try {
            next.currentTime = 0;
          } catch (_) {}
          next.play().catch(function () {});
        } else if (loop && visible.length > 0) {
          const first = visible[0];
          try {
            el.pause();
          } catch (_) {}
          try {
            first.currentTime = 0;
          } catch (_) {}
          first.play().catch(function () {});
        }
      });
    });

    if (playAllBtn) {
      playAllBtn.addEventListener("click", function () {
        playAll = !playAll;
        playAllBtn.setAttribute("aria-pressed", playAll ? "true" : "false");
        setPillStyle(playAllBtn, playAll);
        syncLoopAttributes();
        if (playAll) {
          const visible = getVisiblePlayables();
          if (visible.length > 0) {
            try {
              visible[0].currentTime = 0;
            } catch (_) {}
            visible[0].play().catch(function () {});
          }
        }
      });
    }

    if (loopBtn) {
      loopBtn.addEventListener("click", function () {
        loop = !loop;
        loopBtn.setAttribute("aria-pressed", loop ? "true" : "false");
        setPillStyle(loopBtn, loop);
        syncLoopAttributes();
      });
    }

    filterBtns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        selectedPart = btn.getAttribute("data-filter-part") || "ALL";
        filterBtns.forEach(function (b) {
          setPillStyle(b, b === btn);
        });
        let visibleCount = 0;
        groupSections.forEach(function (sec) {
          const pt = sec.getAttribute("data-group-part");
          const show = selectedPart === "ALL" || pt === selectedPart;
          sec.classList.toggle("hidden", !show);
          if (show) {
            visibleCount++;
          } else {
            sec.querySelectorAll(".uv-playable-media").forEach(function (el) {
              if (!el.paused) el.pause();
            });
          }
        });
        if (emptyMsg && groupSections.length > 0) {
          emptyMsg.classList.toggle("hidden", visibleCount > 0);
        }
      });
    });

    // Remove Song Media buttons
    document.querySelectorAll(".uv-remove-song-media-btn").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const mediaId = btn.getAttribute("data-media-id");
        const label = btn.getAttribute("data-media-label") || "this item";
        if (!window.confirm('Remove "' + label + '"?')) return;
        const fd = new FormData();
        fd.set("uv_action", "remove_song_media");
        fd.set("mediaId", mediaId);
        await postAction(fd);
        window.location.reload();
      });
    });

    // Add Song Media Form
    const addMediaForm = document.getElementById("uv-add-song-media-form");
    if (addMediaForm) {
      let mediaMode = "paste";
      const tabs = addMediaForm.querySelectorAll(".uv-media-mode-tab");
      const pasteWrap = document.getElementById("uv-song-media-paste-wrap");
      const uploadWrap = document.getElementById("uv-song-media-upload-wrap");
      const errEl = document.getElementById("uv-add-song-media-error");
      const submitBtn = document.getElementById("uv-add-song-media-submit");

      function setMediaError(msg) {
        if (!errEl) return;
        errEl.innerHTML = msg ? '<p class="text-sm text-red-600">' + escapeHtml(msg) + "</p>" : "";
      }

      tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
          mediaMode = tab.getAttribute("data-mode") || "paste";
          tabs.forEach(function (t) {
            if (t === tab) {
              t.className = "uv-media-mode-tab border-b-2 border-ink px-1 pb-2 font-medium text-ink";
            } else {
              t.className = "uv-media-mode-tab px-1 pb-2 text-ink/50 hover:text-ink";
            }
          });
          if (pasteWrap) pasteWrap.classList.toggle("hidden", mediaMode !== "paste");
          if (uploadWrap) uploadWrap.classList.toggle("hidden", mediaMode !== "upload");
          setMediaError("");
        });
      });

      addMediaForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        setMediaError("");
        const songId = addMediaForm.getAttribute("data-song-id");
        const labelInput = addMediaForm.querySelector('input[name="label"]');
        const partSelect = addMediaForm.querySelector('select[name="part"]');
        const urlInput = addMediaForm.querySelector('input[name="mediaUrl"]');
        const fileInput = addMediaForm.querySelector('input[name="file"]');

        const label = labelInput ? labelInput.value.trim() : "";
        const part = partSelect ? partSelect.value : "S";
        const url = urlInput ? urlInput.value.trim() : "";
        const file = fileInput && fileInput.files ? fileInput.files[0] : null;

        if (!label || (mediaMode === "paste" ? !url : !file)) {
          setMediaError(
            mediaMode === "paste"
              ? "URL, Label, and Voice are all required."
              : "A file, Label, and Voice are all required."
          );
          return;
        }

        if (mediaMode === "upload" && file && file.size > 20 * 1024 * 1024) {
          setMediaError("File is too large — max 20MB.");
          return;
        }

        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = "Adding…";
        }

        try {
          const fd = new FormData();
          fd.set("uv_action", "add_song_media");
          fd.set("songId", songId);
          fd.set("part", part);
          fd.set("label", label);
          if (mediaMode === "paste") {
            fd.set("mediaUrl", url);
          } else if (file) {
            fd.set("file", file);
          }

          const res = await postAction(fd);
          if (res.error) {
            setMediaError(res.error);
            return;
          }
          window.location.reload();
        } catch (err) {
          setMediaError("Upload failed — please try again.");
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Add Media";
          }
        }
      });
    }
  }

  // ---------------------------------------------------------------------------
  // 7. Sheet Music Page: Add & Remove PDF
  // ---------------------------------------------------------------------------
  const addSheetForm = document.getElementById("uv-add-sheet-form");
  if (addSheetForm) {
    addSheetForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const errEl = document.getElementById("uv-add-sheet-error");
      const submitBtn = document.getElementById("uv-add-sheet-submit");
      if (errEl) errEl.classList.add("hidden");

      const songId = addSheetForm.getAttribute("data-song-id");
      const fileInput = addSheetForm.querySelector('input[name="file"]');
      const labelInput = addSheetForm.querySelector('input[name="label"]');
      const file = fileInput && fileInput.files ? fileInput.files[0] : null;
      const label = labelInput ? labelInput.value.trim() : "";

      if (!file || !label) {
        if (errEl) {
          errEl.textContent = "A PDF file and a label are both required.";
          errEl.classList.remove("hidden");
        }
        return;
      }

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Adding…";
      }

      try {
        const fd = new FormData();
        fd.set("uv_action", "add_sheet_music");
        fd.set("songId", songId);
        fd.set("label", label);
        fd.set("file", file);
        const res = await postAction(fd);
        if (res.error) {
          if (errEl) {
            errEl.textContent = res.error;
            errEl.classList.remove("hidden");
          }
          return;
        }
        window.location.reload();
      } catch (err) {
        if (errEl) {
          errEl.textContent = "Upload failed — please try again.";
          errEl.classList.remove("hidden");
        }
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Add Sheet Music";
        }
      }
    });
  }

  document.querySelectorAll(".uv-remove-sheet-btn").forEach(function (btn) {
    btn.addEventListener("click", async function () {
      if (!window.confirm("Remove this sheet music PDF?")) return;
      const fd = new FormData();
      fd.set("uv_action", "remove_sheet_music");
      fd.set("sheetMusicId", btn.getAttribute("data-sheet-id"));
      await postAction(fd);
      window.location.reload();
    });
  });

  // ---------------------------------------------------------------------------
  // 8. Lyrics Viewer Filter Pills & Replace Lyrics Editor
  // ---------------------------------------------------------------------------
  const lyricsPills = document.getElementById("uv-lyrics-filter-pills");
  if (lyricsPills) {
    const btns = lyricsPills.querySelectorAll(".uv-lyric-filter-btn");
    const cards = document.querySelectorAll(".uv-lyric-section-card");
    const emptyEl = document.getElementById("uv-lyrics-empty-filter");

    btns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        const filter = btn.getAttribute("data-voice-filter") || "ALL";
        btns.forEach(function (b) {
          if (b === btn) {
            b.classList.remove("border-ink/20", "bg-white", "text-ink");
            b.classList.add("border-ink", "bg-ink", "text-white");
          } else {
            b.classList.remove("border-ink", "bg-ink", "text-white");
            b.classList.add("border-ink/20", "bg-white", "text-ink");
          }
        });
        let shown = 0;
        cards.forEach(function (card) {
          const tags = (card.getAttribute("data-voice-tags") || "").split(",");
          const match = filter === "ALL" || tags.includes("SATB") || tags.includes(filter);
          card.classList.toggle("hidden", !match);
          if (match) shown++;
        });
        if (emptyEl) emptyEl.classList.toggle("hidden", shown > 0);
      });
    });
  }

  const replaceLyricsForm = document.getElementById("uv-replace-lyrics-form");
  if (replaceLyricsForm) {
    replaceLyricsForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const hasExisting = replaceLyricsForm.getAttribute("data-has-existing") === "1";
      if (hasExisting && !window.confirm("Replace all existing lyrics for this song?")) {
        return;
      }
      const songId = replaceLyricsForm.getAttribute("data-song-id");
      const rawText = document.getElementById("uv-lyrics-raw-textarea").value;
      const errEl = document.getElementById("uv-replace-lyrics-error");
      if (errEl) errEl.classList.add("hidden");

      const fd = new FormData();
      fd.set("uv_action", "replace_song_lyrics");
      fd.set("songId", songId);
      fd.set("rawText", rawText);
      const res = await postAction(fd);
      if (res.error) {
        if (errEl) {
          errEl.textContent = res.error;
          errEl.classList.remove("hidden");
        }
        return;
      }
      window.location.href = "/songs/" + encodeURIComponent(songId) + "/lyrics";
    });
  }

  // ---------------------------------------------------------------------------
  // 9. Profile View/Edit Toggle, Photo Upload/Remove, and Password Update
  // ---------------------------------------------------------------------------
  const profileRoot = document.getElementById("uv-profile-root");
  if (profileRoot) {
    const VOICE_LABELS = {
      S: "Soprano (S)",
      A: "Alto (A)",
      T: "Tenor (T)",
      B: "Bass (B)",
    };

    let savedBio = profileRoot.getAttribute("data-initial-bio") || "";
    let savedVoice = profileRoot.getAttribute("data-initial-voice") || "";
    let savedInstrument = profileRoot.getAttribute("data-initial-instrument") || "";
    let savedPhone = profileRoot.getAttribute("data-initial-phone") || "";
    let currentPhotoUrl = profileRoot.getAttribute("data-initial-photo") || "";
    const userName = profileRoot.getAttribute("data-user-name") || "U";

    function renderAvatarHtml(url) {
      if (url) {
        return (
          '<img src="' +
          url.replace(/"/g, "&quot;") +
          '" alt="' +
          userName.replace(/"/g, "&quot;") +
          '" data-testid="profile-photo" class="h-16 w-16 rounded-full object-cover" />'
        );
      }
      const initial = (userName.charAt(0) || "?").toUpperCase();
      return (
        '<div data-testid="profile-photo-placeholder" class="flex h-16 w-16 items-center justify-center rounded-full bg-ink/10 text-xl font-semibold text-ink/50">' +
        initial +
        "</div>"
      );
    }

    function renderFlatView() {
      profileRoot.innerHTML =
        '<div class="mb-8 rounded-lg border border-ink/10 bg-white p-4 text-sm shadow-sm">' +
        '<div class="mb-4 flex items-center justify-between gap-4">' +
        '<div class="flex items-center gap-4">' +
        renderAvatarHtml(currentPhotoUrl) +
        "<div>" +
        '<p class="font-medium text-ink">' +
        escapeHtml(userName) +
        "</p>" +
        "</div>" +
        "</div>" +
        '<button type="button" id="uv-profile-edit-btn" class="rounded-full border border-ink/20 px-4 py-1.5 text-sm text-ink hover:bg-ink/5">Edit</button>' +
        "</div>" +
        '<dl class="mt-4 flex flex-col gap-3 border-t border-ink/10 pt-4">' +
        '<div><dt class="text-xs text-ink/50">Bio</dt><dd class="mt-0.5 whitespace-pre-line text-ink">' +
        (savedBio ? escapeHtml(savedBio) : "—") +
        "</dd></div>" +
        '<div><dt class="text-xs text-ink/50">Voice</dt><dd class="mt-0.5 text-ink">' +
        (savedVoice && VOICE_LABELS[savedVoice] ? escapeHtml(VOICE_LABELS[savedVoice]) : "—") +
        "</dd></div>" +
        '<div><dt class="text-xs text-ink/50">Instrument</dt><dd class="mt-0.5 text-ink">' +
        (savedInstrument ? escapeHtml(savedInstrument) : "—") +
        "</dd></div>" +
        '<div><dt class="text-xs text-ink/50">Phone</dt><dd class="mt-0.5 text-ink">' +
        (savedPhone ? escapeHtml(savedPhone) : "—") +
        "</dd></div>" +
        "</dl>" +
        "</div>";

      const editBtn = document.getElementById("uv-profile-edit-btn");
      if (editBtn) {
        editBtn.addEventListener("click", renderEditView);
      }
    }

    function renderEditView() {
      profileRoot.innerHTML =
        '<form id="uv-profile-edit-form" class="mb-8 flex flex-col gap-4 rounded-lg border border-ink/10 bg-white p-4 shadow-sm">' +
        '<div class="flex items-center gap-4">' +
        '<div id="uv-edit-avatar-slot">' +
        renderAvatarHtml(currentPhotoUrl) +
        "</div>" +
        '<div class="flex flex-col gap-1">' +
        '<label class="cursor-pointer rounded-full border border-ink/20 px-3 py-1 text-xs text-ink hover:bg-ink/5">' +
        '<span id="uv-photo-btn-label">' +
        (currentPhotoUrl ? "Change photo" : "Upload photo") +
        "</span>" +
        '<input id="uv-profile-file-input" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="hidden" />' +
        "</label>" +
        '<div id="uv-remove-photo-slot">' +
        (currentPhotoUrl
          ? '<button type="button" id="uv-remove-photo-btn" class="text-left text-xs text-red-600 hover:underline">Remove photo</button>'
          : "") +
        "</div>" +
        '<span class="text-xs text-ink/40">JPEG, PNG, or WebP — max 5MB</span>' +
        "</div>" +
        "</div>" +
        '<label class="flex flex-col gap-1 text-sm">Bio' +
        '<textarea id="uv-edit-bio" rows="3" maxlength="500" placeholder="A short note about yourself…" class="rounded border border-ink/20 px-3 py-2 bg-white"></textarea>' +
        "</label>" +
        '<label class="flex flex-col gap-1 text-sm">Voice' +
        '<select id="uv-edit-voice" class="rounded border border-ink/20 px-3 py-2 bg-white">' +
        '<option value="">Not set</option>' +
        '<option value="S">Soprano (S)</option>' +
        '<option value="A">Alto (A)</option>' +
        '<option value="T">Tenor (T)</option>' +
        '<option value="B">Bass (B)</option>' +
        "</select>" +
        "</label>" +
        '<label class="flex flex-col gap-1 text-sm">Instrument' +
        '<input id="uv-edit-instrument" type="text" maxlength="100" placeholder="e.g. Piano, Acoustic guitar" class="rounded border border-ink/20 px-3 py-2 bg-white" />' +
        "</label>" +
        '<label class="flex flex-col gap-1 text-sm">Phone' +
        '<input id="uv-edit-phone" type="tel" maxlength="30" placeholder="e.g. +254 712 345 678" class="rounded border border-ink/20 px-3 py-2 bg-white" />' +
        "</label>" +
        '<p id="uv-profile-status" data-testid="profile-status" class="hidden text-sm"></p>' +
        '<div class="flex items-center gap-3">' +
        '<button type="submit" class="rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">Save</button>' +
        '<button type="button" id="uv-profile-cancel-btn" class="rounded-full border border-ink/20 px-4 py-2 text-sm text-ink hover:bg-ink/5">Cancel</button>' +
        "</div>" +
        "</form>";

      const bioEl = document.getElementById("uv-edit-bio");
      const voiceEl = document.getElementById("uv-edit-voice");
      const instEl = document.getElementById("uv-edit-instrument");
      const phoneEl = document.getElementById("uv-edit-phone");
      if (bioEl) bioEl.value = savedBio;
      if (voiceEl) voiceEl.value = savedVoice;
      if (instEl) instEl.value = savedInstrument;
      if (phoneEl) phoneEl.value = savedPhone;

      const fileInput = document.getElementById("uv-profile-file-input");
      const statusEl = document.getElementById("uv-profile-status");

      function bindRemovePhotoBtn() {
        const rmBtn = document.getElementById("uv-remove-photo-btn");
        if (rmBtn) {
          rmBtn.addEventListener("click", async function () {
            const fd = new FormData();
            fd.set("uv_action", "remove_profile_photo");
            const res = await postAction(fd);
            if (!res.error) {
              currentPhotoUrl = "";
              document.getElementById("uv-edit-avatar-slot").innerHTML = renderAvatarHtml("");
              document.getElementById("uv-remove-photo-slot").innerHTML = "";
              document.getElementById("uv-photo-btn-label").textContent = "Upload photo";
            }
          });
        }
      }
      bindRemovePhotoBtn();

      if (fileInput) {
        fileInput.addEventListener("change", async function () {
          const file = fileInput.files && fileInput.files[0];
          if (!file) return;
          const fd = new FormData();
          fd.set("uv_action", "upload_profile_photo");
          fd.set("file", file);
          const res = await postAction(fd);
          if (res.error) {
            if (statusEl) {
              statusEl.textContent = res.error;
              statusEl.className = "text-sm text-red-600";
            }
            return;
          }
          currentPhotoUrl = res.photoUrl;
          document.getElementById("uv-edit-avatar-slot").innerHTML = renderAvatarHtml(currentPhotoUrl);
          document.getElementById("uv-photo-btn-label").textContent = "Change photo";
          document.getElementById("uv-remove-photo-slot").innerHTML =
            '<button type="button" id="uv-remove-photo-btn" class="text-left text-xs text-red-600 hover:underline">Remove photo</button>';
          bindRemovePhotoBtn();
        });
      }

      const cancelBtn = document.getElementById("uv-profile-cancel-btn");
      if (cancelBtn) {
        cancelBtn.addEventListener("click", function () {
          renderFlatView();
        });
      }

      const editForm = document.getElementById("uv-profile-edit-form");
      if (editForm) {
        editForm.addEventListener("submit", async function (e) {
          e.preventDefault();
          const fd = new FormData();
          fd.set("uv_action", "update_profile");
          fd.set("bio", bioEl ? bioEl.value : "");
          fd.set("voicePart", voiceEl ? voiceEl.value : "");
          fd.set("instrument", instEl ? instEl.value : "");
          fd.set("phone", phoneEl ? phoneEl.value : "");
          const res = await postAction(fd);
          if (res.error) {
            if (statusEl) {
              statusEl.textContent = res.error;
              statusEl.className = "text-sm text-red-600";
            }
            return;
          }
          savedBio = res.bio || "";
          savedVoice = res.voicePart || "";
          savedInstrument = res.instrument || "";
          savedPhone = res.phone || "";
          renderFlatView();
        });
      }
    }

    const initEditBtn = document.getElementById("uv-profile-edit-btn");
    if (initEditBtn) {
      initEditBtn.addEventListener("click", renderEditView);
    }
  }

  const profilePwForm = document.getElementById("uv-profile-password-form");
  if (profilePwForm) {
    profilePwForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const msgEl = document.getElementById("uv-profile-pw-msg");
      const fd = new FormData(profilePwForm);
      fd.set("uv_action", "change_own_password");
      const res = await postAction(fd);
      if (msgEl) {
        if (res.error) {
          msgEl.textContent = res.error;
          msgEl.className = "text-sm text-red-600";
        } else {
          msgEl.textContent = "Password updated.";
          msgEl.className = "text-sm text-green-700";
          profilePwForm.reset();
        }
      }
    });
  }

  // ---------------------------------------------------------------------------
  // 10. Admin Song Editor (/admin/songs/new, /admin/songs/<id>/edit)
  // ---------------------------------------------------------------------------
  const songEditorForm = document.getElementById("uv-song-editor-form");
  if (songEditorForm) {
    const cancelBtn = document.getElementById("uv-song-editor-cancel");
    if (cancelBtn) {
      cancelBtn.addEventListener("click", function () {
        window.location.href = cancelBtn.getAttribute("data-cancel-url") || "/songs";
      });
    }

    songEditorForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const songId = songEditorForm.getAttribute("data-song-id") || "";
      const title = songEditorForm.querySelector('input[name="title"]').value;
      const composer = songEditorForm.querySelector('input[name="composer"]').value;
      const lyricist = songEditorForm.querySelector('input[name="lyricist"]').value;
      const arranger = songEditorForm.querySelector('input[name="arranger"]').value;
      const errEl = document.getElementById("uv-song-editor-error");
      if (errEl) errEl.classList.add("hidden");

      const payload = {
        title: title,
        composer: composer,
        lyricist: lyricist,
        arranger: arranger,
      };

      const fd = new FormData();
      fd.set("uv_action", "save_song");
      fd.set("songId", songId);
      fd.set("payload", JSON.stringify(payload));

      const res = await postAction(fd);
      if (res.error) {
        if (errEl) {
          errEl.textContent = res.error;
          errEl.classList.remove("hidden");
        }
        return;
      }
      window.location.href = songId ? "/songs/" + encodeURIComponent(songId) : "/songs";
    });
  }

  // ---------------------------------------------------------------------------
  // 11. Admin Members Page (/admin/members)
  // ---------------------------------------------------------------------------
  const inviteForm = document.getElementById("uv-invite-member-form");
  if (inviteForm) {
    inviteForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const errEl = document.getElementById("uv-invite-error");
      const linkBox = document.getElementById("uv-invite-link-box");
      const linkCode = document.getElementById("uv-invite-link-code");
      if (errEl) errEl.classList.add("hidden");
      if (linkBox) linkBox.classList.add("hidden");

      const fd = new FormData(inviteForm);
      fd.set("uv_action", "invite_member");
      const res = await postAction(fd);
      if (res.error) {
        if (errEl) {
          errEl.textContent = res.error;
          errEl.classList.remove("hidden");
        }
        return;
      }
      if (res.inviteLink && linkBox && linkCode) {
        linkCode.textContent = res.inviteLink;
        linkBox.classList.remove("hidden");
      }
    });
  }

  document.querySelectorAll(".uv-member-row").forEach(function (row) {
    const memberId = row.getAttribute("data-member-id");
    const memberName = row.getAttribute("data-member-name") || "this member";
    let currentRole = row.getAttribute("data-member-role") || "chorister";
    const statusAttr = row.getAttribute("data-member-status") || "active";

    const errEl = row.querySelector(".uv-member-error");
    const resetBox = row.querySelector(".uv-member-reset-box");
    const resetCode = row.querySelector(".uv-member-reset-code");
    const resetBtn = row.querySelector(".uv-member-reset-btn");
    const roleSelect = row.querySelector(".uv-member-role-select");
    const statusBtn = row.querySelector(".uv-member-status-btn");
    const deleteBtn = row.querySelector(".uv-member-delete-btn");

    if (resetBtn) {
      resetBtn.addEventListener("click", async function () {
        if (errEl) errEl.classList.add("hidden");
        resetBtn.disabled = true;
        resetBtn.textContent = "Generating…";
        const fd = new FormData();
        fd.set("uv_action", "generate_member_reset_link");
        fd.set("memberId", memberId);
        const res = await postAction(fd);
        resetBtn.disabled = false;
        resetBtn.textContent = "Reset link";
        if (res.error) {
          if (errEl) {
            errEl.textContent = res.error;
            errEl.classList.remove("hidden");
          }
          return;
        }
        if (res.resetLink && resetBox && resetCode) {
          resetCode.textContent = res.resetLink;
          resetBox.classList.remove("hidden");
        }
      });
    }

    if (roleSelect) {
      roleSelect.addEventListener("change", async function () {
        const nextRole = roleSelect.value;
        if (errEl) errEl.classList.add("hidden");
        const fd = new FormData();
        fd.set("uv_action", "update_member_role");
        fd.set("memberId", memberId);
        fd.set("role", nextRole);
        const res = await postAction(fd);
        if (res.error) {
          roleSelect.value = currentRole;
          if (errEl) {
            errEl.textContent = res.error;
            errEl.classList.remove("hidden");
          }
          return;
        }
        currentRole = nextRole;
      });
    }

    if (statusBtn) {
      statusBtn.addEventListener("click", async function () {
        if (errEl) errEl.classList.add("hidden");
        const nextStatus = statusAttr === "disabled" ? "active" : "disabled";
        const fd = new FormData();
        fd.set("uv_action", "set_member_status");
        fd.set("memberId", memberId);
        fd.set("status", nextStatus);
        const res = await postAction(fd);
        if (res.error) {
          if (errEl) {
            errEl.textContent = res.error;
            errEl.classList.remove("hidden");
          }
          return;
        }
        window.location.reload();
      });
    }

    if (deleteBtn) {
      deleteBtn.addEventListener("click", async function () {
        if (!window.confirm("Delete " + memberName + "? This cannot be undone.")) return;
        if (errEl) errEl.classList.add("hidden");
        const fd = new FormData();
        fd.set("uv_action", "delete_member");
        fd.set("memberId", memberId);
        const res = await postAction(fd);
        if (res.error) {
          if (errEl) {
            errEl.textContent = res.error;
            errEl.classList.remove("hidden");
          }
          return;
        }
        window.location.reload();
      });
    }
  });

  // ---------------------------------------------------------------------------
  // 12. Admin About Page (/admin/about)
  // ---------------------------------------------------------------------------
  function setupLinkInsertWidget(container, textarea) {
    if (!container || !textarea) return;
    function renderClosed() {
      container.innerHTML =
        '<button type="button" class="uv-open-link-insert self-start text-xs text-ink underline hover:no-underline">+ Insert link</button>';
      container.querySelector(".uv-open-link-insert").addEventListener("click", renderOpen);
    }
    function renderOpen() {
      const selText =
        textarea.selectionStart !== textarea.selectionEnd
          ? textarea.value.slice(textarea.selectionStart, textarea.selectionEnd)
          : "";
      container.innerHTML =
        '<div class="flex flex-col gap-2 rounded border border-ink/10 bg-ink/5 p-3 text-sm">' +
        '<label class="flex flex-col gap-1">Link text<input type="text" class="uv-li-text rounded border border-ink/20 px-2 py-1 bg-white" /></label>' +
        '<label class="flex flex-col gap-1">URL<input type="text" placeholder="https://..." class="uv-li-url rounded border border-ink/20 px-2 py-1 bg-white" /></label>' +
        '<div class="flex gap-3">' +
        '<button type="button" class="uv-li-insert rounded-full bg-ink px-3 py-1 text-xs text-white hover:opacity-90">Insert</button>' +
        '<button type="button" class="uv-li-cancel text-xs text-ink/50 hover:text-ink">Cancel</button>' +
        "</div></div>";
      const txtIn = container.querySelector(".uv-li-text");
      const urlIn = container.querySelector(".uv-li-url");
      txtIn.value = selText;
      container.querySelector(".uv-li-cancel").addEventListener("click", renderClosed);
      container.querySelector(".uv-li-insert").addEventListener("click", function () {
        const lt = txtIn.value.trim();
        const lu = urlIn.value.trim();
        if (!lt || !lu) return;
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        const snippet = "[" + lt + "](" + lu + ")";
        textarea.value = textarea.value.slice(0, start) + snippet + textarea.value.slice(end);
        renderClosed();
      });
    }
    renderClosed();
  }

  document.querySelectorAll(".uv-about-section-card").forEach(function (card) {
    const sectionId = card.getAttribute("data-section-id");
    let initTitle = card.getAttribute("data-initial-title") || "";
    let initBody = card.getAttribute("data-initial-body") || "";
    const displayDiv = card.querySelector(".uv-about-section-display");
    const editorDiv = card.querySelector(".uv-about-section-editor");

    const editBtn = card.querySelector(".uv-about-sec-edit-btn");
    const deleteBtn = card.querySelector(".uv-about-sec-delete-btn");
    const moveBtns = card.querySelectorAll(".uv-about-move-btn");

    if (editBtn) {
      editBtn.addEventListener("click", function () {
        displayDiv.classList.add("hidden");
        editorDiv.classList.remove("hidden");
        editorDiv.classList.add("flex");
        editorDiv.innerHTML =
          '<label class="flex flex-col gap-1 text-sm">Title (optional)' +
          '<input type="text" class="uv-sec-edit-title rounded border border-ink/20 px-3 py-2 bg-white" />' +
          "</label>" +
          '<label class="flex flex-col gap-1 text-sm">Body <span class="text-red-600">*</span>' +
          '<textarea rows="5" required class="uv-sec-edit-body rounded border border-ink/20 px-3 py-2 bg-white"></textarea>' +
          '<span class="text-xs text-ink/50">Blank lines start a new paragraph. A pasted URL auto-links.</span>' +
          "</label>" +
          '<div class="uv-sec-li-slot"></div>' +
          '<p class="uv-sec-edit-err hidden text-sm text-red-600"></p>' +
          '<div class="flex gap-3">' +
          '<button type="button" class="uv-sec-save-btn self-start rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">Save</button>' +
          '<button type="button" class="uv-sec-cancel-btn text-sm text-ink/50 hover:text-ink">Cancel</button>' +
          "</div>";

        const tIn = editorDiv.querySelector(".uv-sec-edit-title");
        const bIn = editorDiv.querySelector(".uv-sec-edit-body");
        tIn.value = initTitle;
        bIn.value = initBody;
        setupLinkInsertWidget(editorDiv.querySelector(".uv-sec-li-slot"), bIn);

        editorDiv.querySelector(".uv-sec-cancel-btn").addEventListener("click", function () {
          editorDiv.innerHTML = "";
          editorDiv.classList.add("hidden");
          displayDiv.classList.remove("hidden");
        });

        editorDiv.querySelector(".uv-sec-save-btn").addEventListener("click", async function () {
          const fd = new FormData();
          fd.set("uv_action", "update_about_section");
          fd.set("id", sectionId);
          fd.set("title", tIn.value);
          fd.set("body", bIn.value);
          const res = await postAction(fd);
          if (res.error) {
            const errP = editorDiv.querySelector(".uv-sec-edit-err");
            errP.textContent = res.error;
            errP.classList.remove("hidden");
            return;
          }
          window.location.reload();
        });
      });
    }

    if (deleteBtn) {
      deleteBtn.addEventListener("click", async function () {
        if (!window.confirm("Delete this section?")) return;
        const fd = new FormData();
        fd.set("uv_action", "delete_about_section");
        fd.set("id", sectionId);
        await postAction(fd);
        window.location.reload();
      });
    }

    moveBtns.forEach(function (mBtn) {
      mBtn.addEventListener("click", async function () {
        const dir = mBtn.getAttribute("data-dir") || "up";
        const fd = new FormData();
        fd.set("uv_action", "move_about_block");
        fd.set("id", sectionId);
        fd.set("kind", "section");
        fd.set("direction", dir);
        await postAction(fd);
        window.location.reload();
      });
    });
  });

  document.querySelectorAll(".uv-about-media-card").forEach(function (card) {
    const mediaId = card.getAttribute("data-media-id");
    const rmBtn = card.querySelector(".uv-about-media-remove-btn");
    const moveBtns = card.querySelectorAll(".uv-about-media-move-btn");

    if (rmBtn) {
      rmBtn.addEventListener("click", async function () {
        if (!window.confirm("Remove this media item?")) return;
        const fd = new FormData();
        fd.set("uv_action", "remove_about_media");
        fd.set("id", mediaId);
        await postAction(fd);
        window.location.reload();
      });
    }

    moveBtns.forEach(function (mBtn) {
      mBtn.addEventListener("click", async function () {
        const dir = mBtn.getAttribute("data-dir") || "up";
        const fd = new FormData();
        fd.set("uv_action", "move_about_block");
        fd.set("id", mediaId);
        fd.set("kind", "media");
        fd.set("direction", dir);
        await postAction(fd);
        window.location.reload();
      });
    });
  });

  const addAboutSecForm = document.getElementById("uv-add-about-section-form");
  if (addAboutSecForm) {
    const bodyTa = addAboutSecForm.querySelector('textarea[name="body"]');
    const liSlot = addAboutSecForm.querySelector(".uv-link-insert-container");
    setupLinkInsertWidget(liSlot, bodyTa);

    addAboutSecForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const fd = new FormData(addAboutSecForm);
      fd.set("uv_action", "create_about_section");
      const res = await postAction(fd);
      if (res.error) {
        const errEl = document.getElementById("uv-add-about-sec-error");
        if (errEl) {
          errEl.textContent = res.error;
          errEl.classList.remove("hidden");
        }
        return;
      }
      window.location.reload();
    });
  }

  const addAboutMediaForm = document.getElementById("uv-add-about-media-form");
  if (addAboutMediaForm) {
    let aboutMediaMode = "paste";
    const tabs = addAboutMediaForm.querySelectorAll(".uv-about-media-tab");
    const pasteWrap = document.getElementById("uv-about-media-paste-wrap");
    const uploadWrap = document.getElementById("uv-about-media-upload-wrap");
    const errEl = document.getElementById("uv-add-about-media-error");

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        aboutMediaMode = tab.getAttribute("data-mode") || "paste";
        tabs.forEach(function (t) {
          if (t === tab) {
            t.className = "uv-about-media-tab border-b-2 border-ink px-1 pb-2 font-medium text-ink";
          } else {
            t.className = "uv-about-media-tab px-1 pb-2 text-ink/50 hover:text-ink";
          }
        });
        if (pasteWrap) pasteWrap.classList.toggle("hidden", aboutMediaMode !== "paste");
        if (uploadWrap) uploadWrap.classList.toggle("hidden", aboutMediaMode !== "upload");
      });
    });

    addAboutMediaForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      if (errEl) errEl.classList.add("hidden");
      const label = addAboutMediaForm.querySelector('input[name="label"]').value.trim();
      const url = addAboutMediaForm.querySelector('input[name="mediaUrl"]').value.trim();
      const fileInput = addAboutMediaForm.querySelector('input[name="file"]');
      const file = fileInput && fileInput.files ? fileInput.files[0] : null;

      if (!label || (aboutMediaMode === "paste" ? !url : !file)) {
        if (errEl) {
          errEl.textContent =
            aboutMediaMode === "paste"
              ? "URL and Label are both required."
              : "A file and Label are both required.";
          errEl.classList.remove("hidden");
        }
        return;
      }

      const fd = new FormData();
      fd.set("uv_action", "add_about_media");
      fd.set("label", label);
      if (aboutMediaMode === "paste") {
        fd.set("mediaUrl", url);
      } else if (file) {
        fd.set("file", file);
      }

      const res = await postAction(fd);
      if (res.error) {
        if (errEl) {
          errEl.textContent = res.error;
          errEl.classList.remove("hidden");
        }
        return;
      }
      window.location.reload();
    });
  }

  // ---------------------------------------------------------------------------
  // 13. Activity Log PageSize Select
  // ---------------------------------------------------------------------------
  const actPageSize = document.getElementById("uv-activity-pagesize-select");
  if (actPageSize) {
    actPageSize.addEventListener("change", function () {
      window.location.href = "/admin/activity?page=1&pageSize=" + encodeURIComponent(actPageSize.value);
    });
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }
})();
