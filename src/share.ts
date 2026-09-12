const shareUrl = new URL('/RVGame/', window.location.origin).href;

const button = document.createElement('button');
button.type = 'button';
button.className = 'rvgame-share';
button.textContent = 'Share RVGame';
button.setAttribute('aria-label', 'Share RVGame');

const style = document.createElement('style');
style.textContent = `
  .rvgame-share {
    position: fixed;
    left: 12px;
    bottom: 12px;
    z-index: 1000;
    border: 1px solid #76552c;
    border-radius: 999px;
    padding: 8px 13px;
    background: #fff7df;
    color: #26382d;
    box-shadow: 0 3px 12px rgb(38 56 45 / 22%);
    font: 700 14px/1.2 Georgia, serif;
    cursor: pointer;
  }
  .rvgame-share:hover,
  .rvgame-share:focus-visible {
    background: #f5dfaa;
    outline: 2px solid #26382d;
    outline-offset: 2px;
  }
`;

async function copyShareLink(): Promise<string> {
  if (navigator.clipboard && window.isSecureContext) {
    await navigator.clipboard.writeText(shareUrl);
    return 'Link copied';
  }

  window.prompt('Copy this link:', shareUrl);
  return 'Copy the link';
}

button.addEventListener('click', async () => {
  const originalLabel = button.textContent ?? 'Share RVGame';

  try {
    if (navigator.share) {
      await navigator.share({
        title: 'RVGame',
        text: 'Try this camper reseller journey game.',
        url: shareUrl,
      });
      button.textContent = 'Shared';
    } else {
      button.textContent = await copyShareLink();
    }
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') return;
    button.textContent = await copyShareLink();
  }

  window.setTimeout(() => {
    button.textContent = originalLabel;
  }, 2200);
});

document.head.append(style);
document.body.append(button);
