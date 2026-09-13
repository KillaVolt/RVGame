const shareUrl = new URL('/RVGame/?new=1', window.location.origin).href;
const shareTitle = 'RVGame';
const shareText = 'Try this camper reseller journey game.';

const trigger = document.createElement('button');
trigger.type = 'button';
trigger.className = 'rvgame-share-trigger';
trigger.setAttribute('aria-label', 'Share RVGame');
trigger.innerHTML = `
  <svg viewBox="0 0 24 24" aria-hidden="true">
    <circle cx="18" cy="5" r="2.5"></circle>
    <circle cx="6" cy="12" r="2.5"></circle>
    <circle cx="18" cy="19" r="2.5"></circle>
    <path d="M8.2 10.8 15.8 6.2M8.2 13.2l7.6 4.6"></path>
  </svg>
  <span>Share</span>
`;

const dialog = document.createElement('dialog');
dialog.className = 'rvgame-share-dialog';
dialog.innerHTML = `
  <div class="rvgame-share-card">
    <button type="button" class="rvgame-share-close">Close</button>
    <p class="rvgame-share-kicker">Tell someone about RVGame</p>
    <h2>Share the game</h2>
    <div class="rvgame-share-options"></div>
    <button type="button" class="rvgame-share-more">More apps</button>
    <button type="button" class="rvgame-share-copy">Copy link</button>
    <p class="rvgame-share-status" aria-live="polite"></p>
  </div>
`;

const style = document.createElement('style');
style.textContent = `
  .rvgame-share-trigger { position:fixed; left:12px; bottom:12px; z-index:1000; display:inline-flex; align-items:center; gap:7px; border:1px solid #76552c; border-radius:999px; padding:8px 13px; background:#fff7df; color:#26382d; box-shadow:0 3px 12px rgb(38 56 45 / 22%); font:700 14px/1.2 Georgia,serif; cursor:pointer; }
  .rvgame-share-trigger svg { width:18px; height:18px; fill:#b14d32; stroke:#b14d32; stroke-width:1.8; }
  .rvgame-share-trigger:hover,.rvgame-share-trigger:focus-visible { background:#f5dfaa; outline:2px solid #26382d; outline-offset:2px; }
  .rvgame-share-dialog { width:min(92vw,460px); border:1px solid #76552c; border-radius:12px; padding:0; background:#fff9e9; color:#26382d; box-shadow:0 20px 70px rgb(0 0 0 / 35%); }
  .rvgame-share-dialog::backdrop { background:rgb(22 34 27 / 62%); }
  .rvgame-share-card { display:grid; gap:12px; padding:22px; }
  .rvgame-share-card h2,.rvgame-share-card p { margin:0; }
  .rvgame-share-kicker { color:#9b4b30; font-weight:800; text-transform:uppercase; letter-spacing:.08em; font-size:12px; }
  .rvgame-share-close { justify-self:end; border:0; background:transparent; color:#604a2d; cursor:pointer; }
  .rvgame-share-options { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
  .rvgame-share-options a,.rvgame-share-more,.rvgame-share-copy { border:1px solid #76552c; border-radius:6px; padding:10px 12px; background:#f5dfaa; color:#26382d; font:800 14px/1.2 Georgia,serif; text-align:center; text-decoration:none; cursor:pointer; }
  .rvgame-share-options a:hover,.rvgame-share-options a:focus-visible,.rvgame-share-more:hover,.rvgame-share-more:focus-visible,.rvgame-share-copy:hover,.rvgame-share-copy:focus-visible { background:#e8c77d; outline:2px solid #26382d; outline-offset:2px; }
  .rvgame-share-status { min-height:1.2em; color:#9b4b30; font-weight:700; }
`;

const options = [
  ['Facebook', `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`],
  ['X', `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(shareUrl)}`],
  ['Bluesky', `https://bsky.app/intent/compose?text=${encodeURIComponent(`${shareText} ${shareUrl}`)}`],
  ['Reddit', `https://www.reddit.com/submit?url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareTitle)}`],
  ['Email', `mailto:?subject=${encodeURIComponent(shareTitle)}&body=${encodeURIComponent(`${shareText}\n\n${shareUrl}`)}`],
] as const;

const optionHost = dialog.querySelector('.rvgame-share-options') as HTMLDivElement;
for (const [label, href] of options) {
  const link = document.createElement('a');
  link.textContent = label;
  link.href = href;
  if (!href.startsWith('mailto:')) {
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
  }
  optionHost.append(link);
}

const statusMessage = dialog.querySelector('.rvgame-share-status') as HTMLParagraphElement;
const more = dialog.querySelector('.rvgame-share-more') as HTMLButtonElement;

async function copyLink(): Promise<void> {
  if (navigator.clipboard && window.isSecureContext) {
    await navigator.clipboard.writeText(shareUrl);
    statusMessage.textContent = 'Link copied.';
    return;
  }
  window.prompt('Copy this link:', shareUrl);
}

trigger.addEventListener('click', () => dialog.showModal());
dialog.querySelector('.rvgame-share-close')?.addEventListener('click', () => dialog.close());
dialog.querySelector('.rvgame-share-copy')?.addEventListener('click', async () => {
  try {
    await copyLink();
  } catch {
    window.prompt('Copy this link:', shareUrl);
  }
});

if (!navigator.share) {
  more.hidden = true;
} else {
  more.addEventListener('click', async () => {
    try {
      await navigator.share({ title: shareTitle, text: shareText, url: shareUrl });
      statusMessage.textContent = 'Shared.';
    } catch (error) {
      statusMessage.textContent = error instanceof DOMException && error.name === 'AbortError'
        ? 'Sharing cancelled. You can copy the link instead.'
        : 'The share sheet could not open. Choose an option above instead.';
    }
  });
}

document.head.append(style);
document.body.append(trigger, dialog);
