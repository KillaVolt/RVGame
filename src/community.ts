import { loadCommunity, rateGame, submitFeedback } from './api';
import type { CommunitySummary } from './types';

const trigger = document.createElement('button');
trigger.type = 'button';
trigger.className = 'rvgame-community-trigger';
trigger.textContent = 'Help improve RVGame';

const dialog = document.createElement('dialog');
dialog.className = 'rvgame-community-dialog';
dialog.innerHTML = `
  <form method="dialog" class="rvgame-community-card">
    <button type="button" class="rvgame-community-close" aria-label="Close">Close</button>
    <p class="rvgame-community-kicker">Help shape RVGame</p>
    <h2>Rate it or leave a quick note</h2>
    <p class="rvgame-rating-summary" aria-live="polite">Loading ratings...</p>
    <div class="rvgame-rating-buttons" aria-label="Rate RVGame from 1 to 5"></div>
    <label>
      What is this?
      <select name="category">
        <option value="idea">Feature idea</option>
        <option value="bug">Bug</option>
        <option value="other">Other</option>
      </select>
    </label>
    <label>
      Tell me what happened or what you want
      <textarea name="message" minlength="3" maxlength="2000" required placeholder="A sentence or two is enough."></textarea>
    </label>
    <label class="rvgame-honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
    <button class="rvgame-feedback-submit" type="submit" value="submit">Send note</button>
    <p class="rvgame-community-status" aria-live="polite"></p>
  </form>
`;

const style = document.createElement('style');
style.textContent = `
  .rvgame-community-trigger { position:fixed; left:140px; bottom:12px; z-index:1000; border:1px solid #76552c; border-radius:999px; padding:8px 13px; background:#fff7df; color:#26382d; box-shadow:0 3px 12px rgb(38 56 45 / 22%); font:700 14px/1.2 Georgia,serif; cursor:pointer; }
  .rvgame-community-trigger:hover,.rvgame-community-trigger:focus-visible { background:#f5dfaa; outline:2px solid #26382d; outline-offset:2px; }
  .rvgame-community-dialog { width:min(92vw,500px); border:1px solid #76552c; border-radius:12px; padding:0; background:#fff9e9; color:#26382d; box-shadow:0 20px 70px rgb(0 0 0 / 35%); }
  .rvgame-community-dialog::backdrop { background:rgb(22 34 27 / 62%); }
  .rvgame-community-card { display:grid; gap:12px; padding:22px; }
  .rvgame-community-card h2,.rvgame-community-card p { margin:0; }
  .rvgame-community-kicker { color:#9b4b30; font-weight:800; text-transform:uppercase; letter-spacing:.08em; font-size:12px; }
  .rvgame-community-close { justify-self:end; border:0; background:transparent; color:#604a2d; cursor:pointer; }
  .rvgame-rating-buttons { display:flex; flex-wrap:wrap; gap:7px; }
  .rvgame-rating-buttons button,.rvgame-feedback-submit { border:1px solid #76552c; border-radius:6px; padding:8px 12px; background:#f5dfaa; color:#26382d; font-weight:800; cursor:pointer; }
  .rvgame-rating-buttons button[aria-pressed="true"] { background:#b14d32; color:#fff; }
  .rvgame-community-card label { display:grid; gap:5px; font-weight:700; }
  .rvgame-community-card select,.rvgame-community-card textarea { width:100%; box-sizing:border-box; border:1px solid #9b805c; border-radius:5px; padding:9px; background:#fffdf6; color:#26382d; font:inherit; }
  .rvgame-community-card textarea { min-height:100px; resize:vertical; }
  .rvgame-honeypot { position:absolute!important; left:-10000px!important; }
  .rvgame-community-status { min-height:1.2em; color:#9b4b30; font-weight:700; }
  @media (max-width:420px) { .rvgame-community-trigger { left:auto; right:12px; } }
`;

const form = dialog.querySelector('form') as HTMLFormElement;
const summary = dialog.querySelector('.rvgame-rating-summary') as HTMLParagraphElement;
const ratingButtons = dialog.querySelector('.rvgame-rating-buttons') as HTMLDivElement;
const status = dialog.querySelector('.rvgame-community-status') as HTMLParagraphElement;
let loaded = false;
let ownerExcluded = false;

dialog.querySelector('.rvgame-community-close')?.addEventListener('click', () => dialog.close());

function renderRatings(data: CommunitySummary): void {
  ownerExcluded = data.ownerExcluded;
  summary.textContent = data.ratingCount === 0
    ? 'No ratings yet.'
    : `${data.averageRating}/5 from ${data.ratingCount} player${data.ratingCount === 1 ? '' : 's'}.`;
  if (ownerExcluded) summary.textContent += ' Owner testing is excluded from all metrics. Your ratings and feedback are disabled.';
  for (const control of form.querySelectorAll<HTMLButtonElement | HTMLTextAreaElement | HTMLSelectElement>('.rvgame-feedback-submit, textarea, select')) control.disabled = ownerExcluded;
  ratingButtons.replaceChildren();
  for (let rating = 1; rating <= 5; rating += 1) {
    const choice = document.createElement('button');
    choice.type = 'button';
    choice.disabled = ownerExcluded;
    choice.textContent = `${rating}/5`;
    choice.setAttribute('aria-pressed', String(data.userRating === rating));
    choice.addEventListener('click', async () => {
      status.textContent = 'Saving rating...';
      try {
        renderRatings(await rateGame(rating));
        status.textContent = 'Rating saved. Thank you.';
      } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'Rating could not be saved.';
      }
    });
    ratingButtons.append(choice);
  }
}

trigger.addEventListener('click', async () => {
  dialog.showModal();
  if (loaded) return;
  try {
    renderRatings(await loadCommunity());
    loaded = true;
  } catch (error) {
    summary.textContent = error instanceof Error ? error.message : 'Ratings could not be loaded.';
  }
});

form.addEventListener('submit', async (event) => {
  const submitter = (event as SubmitEvent).submitter as HTMLButtonElement | null;
  if (submitter?.value !== 'submit') return;
  event.preventDefault();
  const data = new FormData(form);
  status.textContent = 'Sending...';
  try {
    await submitFeedback(String(data.get('category')), String(data.get('message')), String(data.get('website')));
    (form.elements.namedItem('message') as HTMLTextAreaElement).value = '';
    status.textContent = 'Note sent. Thank you.';
  } catch (error) {
    status.textContent = error instanceof Error ? error.message : 'The note could not be sent.';
  }
});

document.head.append(style);
document.body.append(trigger, dialog);
