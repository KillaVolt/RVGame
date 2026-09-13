import { useEffect, useRef, useState } from "react";
import type { ReactNode } from "react";
import { command, loadGame, newGame, resetGame } from "./api";
import type { GameState, HudState, StartOption } from "./types";

const money = (cents: number) =>
  "G$" + (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const distance = (km: number, unit: "km" | "mi") =>
  unit === "km" ? km + " km" : (km * 0.621371).toFixed(1) + " mi";

const mass = (kg: number, unit: "km" | "mi") =>
  unit === "km" ? kg + " kg" : Math.round(kg * 2.20462).toLocaleString() + " lb";

const duration = (hours: number) => hours + (hours === 1 ? " hour" : " hours");

const clock = (hour: number) => String(hour).padStart(2, "0") + ":00";

const stateLabel: Record<HudState, string> = {
  UNKNOWN: "? Unknown",
  LOOKS_OK: "OK Looks OK",
  WATCH: "! Watch",
  FAULT: "X Fault",
  TOWING_BLOCKED: "STOP Towing blocked"
};

export default function App() {
  const [game, setGame] = useState<GameState | null>(null);
  const [loaded, setLoaded] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [setup, setSetup] = useState<StartOption[]>([]);
  const [loadoutId, setLoadoutId] = useState("");
  const [resetRequired, setResetRequired] = useState<string | null>(null);
  const [selectedRouteId, setSelectedRouteId] = useState("");
  const [repayAmount, setRepayAmount] = useState("100");
  const [modal, setModal] = useState<{ title: string; text: string; confirm?: () => void } | null>(null);
  const lock = useRef(false);
  const completion = useRef<HTMLElement>(null);

  useEffect(() => {
    if (game?.status === "completed" && !modal) {
      completion.current?.focus({ preventScroll: true });
      completion.current?.scrollIntoView({ block: "start" });
    }
  }, [game?.status, modal]);

  useEffect(() => {
    loadGame()
      .then((bootstrap) => {
        setGame(bootstrap.state);
        setSetup(bootstrap.setup);
        setResetRequired(bootstrap.resetRequired?.message ?? null);
      })
      .catch((cause: Error) => setError(cause.message))
      .finally(() => setLoaded(true));
  }, []);

  function run(type: string, payload: Record<string, unknown> = {}) {
    if (!game || lock.current) return;
    if (type === "set-unit") { void execute(type, payload); return; }
    const preview = actionPreview(game, type, payload);
    setModal({ title: "Before you commit", text: preview, confirm: () => void execute(type, payload) });
  }

  async function execute(type: string, payload: Record<string, unknown>) {
    if (!game || lock.current) return;
    lock.current = true;
    setBusy(true);
    setError("");
    try {
      const next = await command(game, type, payload);
      setGame(next);
      if (type === "travel" || type === "recover") setSelectedRouteId("");
      if (type !== "set-unit") setModal({
        title: next.status === "completed" ? "You made this one home!" : "Action saved",
        text: `${next.eventLog.at(-1)?.text}\n\nCash: ${money(next.finances.cashCents)} (${money(next.finances.cashCents - game.finances.cashCents)} change). Debt: ${money(next.finances.totalDebtCents)} (${money(next.finances.totalDebtCents - game.finances.totalDebtCents)} change).\nNext: ${next.objective.toLowerCase().replaceAll("_", " ")}.`
      });
    } catch (cause) {
      const message = cause instanceof Error ? cause.message : "The action could not be confirmed. Reload to check your saved campaign.";
      setError(message);
      setModal({ title: "Check this action", text: message });
    } finally {
      lock.current = false;
      setBusy(false);
    }
  }

  async function start() {
    if (!loadoutId) return;
    setBusy(true);
    setError("");
    try {
      setGame(await newGame(loadoutId));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Campaign creation failed.");
    } finally {
      setBusy(false);
    }
  }

  async function reset() {
    if (!confirm("Delete this browser's campaign and start over? This cannot be undone.")) return;
    setBusy(true);
    try {
      await resetGame();
      setGame(null);
      setLoadoutId("");
      setSelectedRouteId("");
      setResetRequired(null);
      setError("");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Reset failed.");
    } finally {
      setBusy(false);
    }
  }

  if (!loaded) return <main className="boot">Opening the garage...</main>;

  if (!game) {
    return (
      <main className="start-screen">
        <div className="start-copy">
          <p className="eyebrow">A camper reseller journey</p>
          <h1>RVGame</h1>
          <p className="provisional">Provisional working title</p>
          <p>
            You paid, the seller is gone, and your brief roadworthy check found nothing obvious.
            Your financed fixer-upper is still three stops from home, with a technician available if you want a deeper inspection before leaving.
          </p>
          <p><b>Your goal:</b> bring the camper home, sell projects, clear the debt, then keep the Sunbeam. This is an open-ended journey with no 60-day deadline.</p>
          <p>Each action shows its price and time before you confirm. “Looks OK” is limited evidence; a quick check can miss hidden faults.</p>
          {resetRequired && <div className="reset-required" role="alert"><p>{resetRequired} The previous campaign remains untouched until you confirm deletion.</p><button onClick={reset} disabled={busy}>Delete the old campaign and continue</button></div>}
          <fieldset className="loadout-picker">
            <legend>Before you left home, what did you prepare for?</legend>
            <div className="loadout-grid">
              {setup.map((option) => (
                <button
                  type="button"
                  className={"loadout-card" + (loadoutId === option.id ? " selected" : "")}
                  aria-pressed={loadoutId === option.id}
                  onClick={() => setLoadoutId(option.id)}
                  key={option.id}
                >
                  <span>{option.name}</span>
                  <strong>{option.tagline}</strong>
                  <small>{option.description}</small>
                  <small><b>Packed:</b> {option.equipment.join(", ")}</small>
                  <small>{option.benefit}</small>
                </button>
              ))}
            </div>
          </fieldset>
          <button onClick={start} disabled={busy || !loadoutId || resetRequired !== null}>Take ownership of the fixer-upper</button>
          {error && <p className="error" role="alert">{error}</p>}
          <a className="source-link" href="https://github.com/KillaVolt/RVGame" target="_blank" rel="noreferrer">Source · AGPL-3.0-only</a>
        </div>
      </main>
    );
  }

  const unit = game.player.distanceUnit;
  const latest = game.eventLog.at(-1);
  const selectedRoute = game.routes.find((route) => route.id === selectedRouteId);
  const locations = new Map(game.mapLocations.map((place) => [place.id, place]));
  const hoursUntilMidnight = 24 - game.hour;
  const paymentCents = /^\d+(\.\d{1,2})?$/.test(repayAmount) ? Math.round(Number(repayAmount) * 100) : 0;
  const maximumPayment = Math.min(game.finances.cashCents, game.finances.totalDebtCents);
  const paymentValid = Number.isSafeInteger(paymentCents) && paymentCents > 0 && paymentCents <= maximumPayment;
  const finished = game.status === "completed";
  const eventPending = game.pendingEvent !== null;

  return (
    <div className="app-shell">
      <a className="source-link" href="https://github.com/KillaVolt/RVGame" target="_blank" rel="noreferrer">Source · AGPL-3.0-only</a>
      <header className="status-strip">
        <div className="brand">
          <span className="tiny">Provisional title</span>
          <strong>RVGame</strong>
        </div>
        <ClockStat day={game.day} hour={game.hour} />
        <Stat label="Location" value={game.player.locationName} />
        <Stat label="Cash" value={money(game.finances.cashCents)} />
        <Stat label="Debt" value={money(game.finances.totalDebtCents)} danger={game.finances.totalDebtCents > 0} />
        <div className="objective"><span>Objective</span><strong>{game.objective}</strong></div>
        <button className="small-button" onClick={() => run("set-unit", { unit: unit === "km" ? "mi" : "km" })}>
          Show {unit === "km" ? "miles" : "km"}
        </button>
        <span className="save-state">{busy ? "Saving…" : error ? "Check action message" : "Saved"}</span>
      </header>

      {modal && <GameDialog title={modal.title} onClose={() => !busy && setModal(null)}>
        <p className="dialog-copy">{modal.text}</p>
        {modal.confirm ? <div className="button-row"><button disabled={busy} onClick={() => setModal(null)}>Cancel</button><button disabled={busy} onClick={modal.confirm}>{busy ? "Saving…" : "Confirm action"}</button></div>
          : <button autoFocus onClick={() => setModal(null)}>Continue</button>}
      </GameDialog>}
      {error && <div className="error-bar" role="alert">{error}</div>}
      {game.pendingEvent && <section className="event-decision" aria-labelledby="event-title">
        <p className="eyebrow">Decision required</p>
        <h2 id="event-title">{game.pendingEvent.title}</h2>
        <p>{game.pendingEvent.text}</p>
        <div className="event-choices">
          {game.pendingEvent.choices.map((choice) => <article key={choice.id}>
            <strong>{choice.label}</strong>
            <p>{choice.preview}</p>
            <small>{choice.cashCostCents > 0 ? money(choice.cashCostCents) + " direct cost · " : "No direct cost · "}{duration(choice.hours)}{choice.recoveryPayableCents > 0 ? ` · ${money(choice.recoveryPayableCents)} recovery payable` : ""}</small>
            {choice.reason && <small>{choice.reason}</small>}
            <button disabled={busy || !choice.available} onClick={() => run("resolve-event", { eventInstanceId: game.pendingEvent!.instanceId, choiceId: choice.id })}>Choose this response</button>
          </article>)}
        </div>
      </section>}
      <details className="play-help">
        <summary>How to play · Your goal</summary>
        <p>Bring the first camper to Maple Junction. Select the next town, then tow; driving alone leaves the camper behind. Inspections reveal issues; DIY trades time for money. Sell as-is or repair first, then buy another project.</p>
        <p>After {game.goal.unlockAfterSales} sales the Sunbeam becomes available. Keep it at home with the listed conditions met, all debt cleared and {money(game.goal.minimumReserveCents)} cash left. There is no day limit. Choosing Make This One Home ends the campaign.</p>
        <ul>{game.goal.requirements.map((check) => <li key={check.label}>{check.met ? "✓" : "○"} {check.label}</li>)}</ul>
      </details>
      {finished && (
        <section ref={completion} tabIndex={-1} className="completion" aria-label="Campaign results">
          <h1>You made this one home!</h1><p>The Sunbeam is yours. Your journey finished on Day {game.day} at {clock(game.hour)}.</p>
          <p>{game.saleCount} campers sold · Cash {money(game.finances.cashCents)} · Debt {money(game.finances.totalDebtCents)}</p>
          <p>Review your sales and history below, or start a new journey.</p>
          <button onClick={reset} disabled={busy}>Start a new journey</button>
        </section>
      )}

      <main className={"dashboard" + (finished ? " finished" : "")}>
        <section className="panel map-panel" aria-labelledby="map-title">
          <div className="panel-heading"><h2 id="map-title">Road map</h2><span>{game.player.locationName}</span></div>
          <div className="map" aria-label="Interactive road map">
            <svg aria-hidden="true" viewBox="0 0 100 100" preserveAspectRatio="none">
              {game.mapRoutes.map((route) => {
                const a = locations.get(route.a);
                const b = locations.get(route.b);
                if (!a || !b) return null;
                return (
                  <line
                    key={route.id}
                    x1={a.x}
                    y1={a.y}
                    x2={b.x}
                    y2={b.y}
                    className={(route.reachable ? "reachable " : "") + (selectedRoute?.id === route.id ? "selected" : "")}
                  />
                );
              })}
            </svg>
            {game.mapLocations.map((place) => {
              const route = game.routes.find((candidate) => candidate.destinationId === place.id);
              return (
                <button
                  type="button"
                  className={"map-stop" + (place.current ? " current" : "") + (route?.id === selectedRoute?.id ? " selected" : "")}
                  style={{ left: place.x + "%", top: place.y + "%" }}
                  disabled={!route || eventPending}
                  onClick={() => route && setSelectedRouteId(route.id)}
                  aria-label={route ? `Select route to ${place.name}` : place.name}
                  key={place.id}
                >
                  <span className="dot">
                    {place.current && <span className="you-marker">YOU</span>}
                    {place.camper && <span className="rv-marker">RV</span>}
                  </span>
                  <span className="place-name">{place.name}</span>
                </button>
              );
            })}
            <div className="map-legend"><span><i className="you-key" />Player</span><span><i className="rv-key" />Camper</span><span><i className="road-key" />Reachable road</span></div>
          </div>
          <div className="route-list">
            {selectedRoute ? (
              <div className="route-row">
                <div><span className="route-kicker">Selected destination</span><strong>{selectedRoute.destinationName}</strong></div>
                <span>{distance(selectedRoute.distanceKm, unit)}</span>
                <button disabled={busy || eventPending || !selectedRoute.canTow} title={selectedRoute.towReason || ""} onClick={() => run("travel", { routeId: selectedRoute.id, mode: "tow" })}>
                  Tow {money(selectedRoute.towCostCents)} | {duration(selectedRoute.towHours)}
                </button>
                <button disabled={busy || eventPending || !selectedRoute.canSolo} onClick={() => run("travel", { routeId: selectedRoute.id, mode: "solo" })}>
                  Drive {money(selectedRoute.soloCostCents)} | {duration(selectedRoute.soloHours)}
                </button>
              </div>
            ) : <p className="muted">Choose a connected town on the map to see its travel choices.</p>}
            {selectedRoute && <p className="route-warning">{selectedRoute.currentWeightKg === null ? "No camper is attached." : `Tow weight ${mass(selectedRoute.currentWeightKg, unit)}; Bluebird limit ${mass(selectedRoute.towLimitKg, unit)}.`}<br/>{selectedRoute.towReason || (game.camper?.hud.some((s) => s.state === "FAULT") ? "A known fault remains. Towing is currently permitted, but an unresolved tire problem can stop a later trip." : "A brief check can miss faults. You can pay a technician before towing.")}<br/>Drive alone leaves your camper at {game.camper?.locationName || "its parking location"}.</p>}
          </div>
        </section>

        <section className="panel project-panel" aria-labelledby="project-title">
          <div className="panel-heading">
            <h2 id="project-title">{game.camper ? game.camper.name : "No current project"}</h2>
            <span>{game.camper ? game.camper.locationName : game.saleCount + " campers sold"}</span>
          </div>
          {game.camper ? (
            <>
              <div className="hud">
                {game.camper.hud.map((system) => (
                  <details className={"hud-item state-" + system.state.toLowerCase()} key={system.id}>
                    <summary><span>{system.label}</span><strong>{stateLabel[system.state]}</strong></summary>
                    <p>{system.evidence}</p>
                  </details>
                ))}
              </div>
              <div className="distance-strip">
                <span>Bluebird odometer <b>{distance(game.towVehicle.odometerKm, unit)}</b></span>
                <span>Camper towed <b>{distance(game.camper.towedKm, unit)}</b></span>
                {game.camper.transportedKm > 0 && <span>Recovered <b>{distance(game.camper.transportedKm, unit)}</b></span>}
                <span>Current tow weight <b>{mass(game.camper.weight.currentKg, unit)}</b></span>
                <span>Camper rating <b>{mass(game.camper.weight.grossVehicleWeightRatingKg, unit)}</b></span>
                <span>Bluebird limit <b>{mass(game.towVehicle.maxTrailerWeightKg, unit)}</b></span>
                <span>Remaining camper payload <b>{mass(game.camper.weight.remainingPayloadKg, unit)}</b></span>
                <span>Project cost <b>{money(game.camper.projectCostCents)}</b></span>
                <span>Approach <b>{game.player.approachName}</b></span>
                <span>Vehicle reference <b>{game.camper.instanceId.slice(0, 8)}</b></span>
              </div>
              <p className="muted">Estimated empty {mass(game.camper.weight.dryKg, unit)} · all tanks full {mass(game.camper.weight.allTanksFullKg, unit)} · tags {game.camper.tags.join(", ")}. Looks OK means no known unresolved issue, not a guarantee.</p>
              <details className="ledger"><summary>Project expenses · {money(game.camper.projectCostCents)}</summary><ExpenseList expenses={game.camper.projectExpenses}/><p>Project profit excludes living costs, interest and recovery bills. Those remain campaign overhead.</p></details>
            </>
          ) : (
            <p className="empty">The garage, Bluebird, cash, debt, and your ability to work or buy remain available.</p>
          )}
        </section>

        <section className="panel market-panel" aria-labelledby="market-title">
          <div className="panel-heading"><h2 id="market-title">Local and market</h2><span>Compact opportunities</span></div>
          <div className="local-context">
            <h3>{game.market.local.phase}</h3>
            <p>{game.market.local.demand.map((demand) => `${demand.label}: ${demand.level}`).join(" · ")}</p>
            <p><b>Service:</b> {game.market.local.technician.available ? `${game.market.local.technician.name}, ${game.market.local.technician.currentPriceBps / 100}% of base price` : "No local technician"}. Fresh fill {game.market.local.water.freshFill ? "available" : "unavailable"}; waste dump {game.market.local.water.wasteDump ? "available" : "unavailable"}.</p>
            <button disabled={busy || eventPending || !game.market.local.intel.available} title={game.market.local.intel.reason || ""} onClick={() => run("buy-intel")}>Buy tomorrow's local forecast from {game.market.local.intel.contact} · {money(game.market.local.intel.costCents)}</button>
            {game.market.local.intel.reason && <small>{game.market.local.intel.reason}</small>}
            {game.market.intelReports.slice(-3).reverse().map((report) => <p className="intel-note" key={report.id}><b>Purchased forecast:</b> {report.text}</p>)}
            <details><summary>Local market history · {game.market.history.length} snapshots</summary>
              {game.market.history.slice().reverse().map((snapshot) => <p key={snapshot.day}><b>Day {snapshot.day}:</b> {snapshot.demand.map((demand) => `${demand.label} ${demand.level}`).join(", ")}; {snapshot.listingCount} listing{snapshot.listingCount === 1 ? "" : "s"}; {snapshot.technicianPriceBps === 0 ? "no technician" : `service ${snapshot.technicianPriceBps / 100}%`}.</p>)}
            </details>
          </div>
          <h3>Campers for sale</h3>
          {game.listings.length === 0 && <p className="muted">No local listings yet. Finish or sell the current project.</p>}
          {game.listings.map((listing) => (
            <article className="dense-row" key={listing.id}>
              <div>
                <strong>{listing.name}{listing.dream ? " - dream target" : ""}</strong>
                <span>{listing.locationName} | {listing.sellerClaim}</span>
                <small>{listing.knownIssues.join(", ") || "No disclosed issue; hidden defects are possible."}</small>
                <small>Dry {mass(listing.dryWeightKg, unit)} · rating {mass(listing.grossVehicleWeightRatingKg, unit)} · expires Day {Math.floor(listing.expiresAtHour / 24) + 1}, {clock(listing.expiresAtHour % 24)}</small>
                <small>Different vehicle · reference {listing.instanceId.slice(0, 8)}</small>
                {listing.buyReason && <small>{listing.buyReason}</small>}
              </div>
              <b>{money(listing.askCents)}</b>
              <button disabled={busy || eventPending || !listing.canBuy} title={listing.buyReason || ""} onClick={() => run("buy", { listingId: listing.id })}>Buy</button>
            </article>
          ))}
          <h3>Local jobs</h3>
          <p><b>{game.work.employer}</b><br/>{game.work.task}. No specialist tools needed.<br/>Accept a start from {clock(game.work.startHour)} to {clock(game.work.lastStartHour)}. Takes {duration(game.work.hours)}, paid on completion. Jobs reopen each morning.</p>
          <div className="button-row">
            <button disabled={busy || eventPending || !game.work.available} onClick={() => run("work")}>Accept job +{money(game.work.grossCents)} | {duration(game.work.hours)}</button>
            {!game.work.available && <button disabled={busy || eventPending} onClick={() => run("rest")}>Rest until jobs open | {duration(game.work.restHours)}</button>}
          </div>
          <h3>Recovery</h3>
          <p>Brings you and the camper to Maple Junction. Creates a bill, even at zero cash; repairs cost extra.</p>
          <div className="button-row">
            <button disabled={busy || eventPending || !game.canRecover} onClick={() => run("recover")}>Recovery to garage +{money(game.recoveryPayableCents)} payable | {duration(game.recoveryHours)}</button>
          </div>
          {!game.canRecover && <p className="muted">{game.camper ? "The camper is already at the garage." : "Buy a camper before requesting recovery."}</p>}
        </section>

        <section className="panel actions-panel" aria-labelledby="actions-title">
          <div className="panel-heading"><h2 id="actions-title">Project actions</h2><span>Review, then confirm</span></div>
          <div className="time-context" aria-label={`Current time Day ${game.day}, ${clock(game.hour)}`}>
            <span>Current time</span>
            <strong>Day {game.day} | {clock(game.hour)}</strong>
            <small>{hoursUntilMidnight} {hoursUntilMidnight === 1 ? "hour" : "hours"} until daily costs and loan interest settle</small>
            <small>Next midnight at your current location: {money(game.daily.nextCostCents)} costs + {money(game.daily.nextInterestCents)} interest added to debt.</small>
          </div>
          {game.camper && (
            <>
              <h3>Weight and tanks</h3>
              <p>Current {mass(game.camper.weight.currentKg, unit)} · camper rating {mass(game.camper.weight.grossVehicleWeightRatingKg, unit)} · Bluebird limit {mass(game.towVehicle.maxTrailerWeightKg, unit)}. Tanks count 1 kg per litre in this fictional Simple model.</p>
              <div className="tank-grid">
                {(["fresh", "grey", "black"] as const).map((tank) => <article key={tank}>
                  <strong>{tank.charAt(0).toUpperCase() + tank.slice(1)} tank</strong>
                  <span>{game.camper!.tankLitres[tank]} / {game.camper!.tankCapacitiesLitres[tank]} L</span>
                  {tank === "fresh" ? <div className="button-row">
                    <button disabled={busy || eventPending || game.camper!.tankLitres.fresh === 0} onClick={() => run("set-tank", { tankId: "fresh", targetLitres: 0 })}>Drain fresh</button>
                    <button disabled={busy || eventPending || !game.market.local.water.freshFill || game.camper!.tankLitres.fresh === game.camper!.tankCapacitiesLitres.fresh || game.finances.cashCents < (game.camper!.tankCapacitiesLitres.fresh - game.camper!.tankLitres.fresh) * game.weightRules.freshWaterCentsPerLitre} onClick={() => run("set-tank", { tankId: "fresh", targetLitres: game.camper!.tankCapacitiesLitres.fresh })}>Fill fresh</button>
                  </div> : <button disabled={busy || eventPending || !game.market.local.water.wasteDump || game.camper!.tankLitres[tank] === 0 || game.finances.cashCents < game.weightRules.wasteDumpCostCents} onClick={() => run("set-tank", { tankId: tank, targetLitres: 0 })}>Dump {tank} · {money(game.weightRules.wasteDumpCostCents)}</button>}
                </article>)}
              </div>
              <div className="button-row">
                <button disabled={busy || eventPending || game.camper.looseLoadKg === 0} onClick={() => run("set-loose-load", { targetKg: Math.max(0, game.camper!.looseLoadKg - game.weightRules.looseLoadStepKg) })}>Remove {mass(game.weightRules.looseLoadStepKg, unit)} loose load</button>
                <button disabled={busy || eventPending || game.camper.looseLoadKg >= game.weightRules.maximumLooseLoadKg} onClick={() => run("set-loose-load", { targetKg: Math.min(game.weightRules.maximumLooseLoadKg, game.camper!.looseLoadKg + game.weightRules.looseLoadStepKg) })}>Add {mass(game.weightRules.looseLoadStepKg, unit)} loose load</button>
              </div>
              <h3>Inspect systems</h3>
              {game.inspection.selfAvailable ? (
                <div className="compact-buttons">
                  {game.camper.hud.map((system) => (
                    <div key={system.id}><button disabled={busy || eventPending || system.selfReason !== null} onClick={() => run("inspect", { systemId: system.id })}>
                      Self-check {system.label} | Free / {duration(game.inspection.selfHours)}
                    </button>{system.selfReason && <small>{system.selfReason}</small>}</div>
                  ))}
                </div>
              ) : (
                <p className="muted">{game.inspection.selfReason}</p>
              )}
              <div className="button-row">
                <button disabled={busy || eventPending || game.inspection.technicianReason !== null} onClick={() => run("technician-inspect")}>
                  Call {game.inspection.technicianName} | {money(game.inspection.technicianCostCents)} / {duration(game.inspection.technicianHours)}
                </button>
              </div>
              <p className="muted">{game.inspection.technicianReason || "Full inspection reveals hidden faults that a self-check can miss."}</p>
              <h3>Known issues</h3>
              {!game.camper.knownDefects.some((d) => !d.resolved) && <p className="muted">No known unresolved defects. {game.inspection.technicianReason === "Full inspection complete." ? "All inspected issues are repaired." : "Further inspection may reveal hidden issues."}</p>}
              {game.repairs.filter((repair) => repair.diyReason !== "No matching known issue." || repair.technicianReason !== "No matching known issue.").map((repair) => (
                <article className="dense-row" key={repair.id}>
                  <div><strong>{repair.label}</strong><span>{repair.systemName}</span><small>{repair.serviceNote}</small>{repair.technicianReason && <small>{repair.technicianReason}</small>}{repair.diyAllowed && repair.diyReason && <small>DIY: {repair.diyReason}</small>}</div>
                  <span>{repair.diyAllowed ? `DIY ${duration(repair.diyHours || 0)}` : "Technician only"}</span>
                  <div className="button-row">
                    {repair.diyAllowed && <button disabled={busy || eventPending || !repair.diyAvailable} title={repair.diyReason || ""} onClick={() => run("repair", { repairId: repair.id, method: "diy" })}>DIY free</button>}
                    <button disabled={busy || eventPending || !repair.technicianAvailable} title={repair.technicianReason || ""} onClick={() => run("repair", { repairId: repair.id, method: "technician" })}>Tech {money(repair.technicianCostCents)} | {duration(repair.technicianHours)}</button>
                  </div>
                </article>
              ))}
              {game.atGarageWithCamper ? (
                <>
                  <h3>Garage improvements</h3>
                  {game.upgrades.map((upgrade) => (
                    <article className="dense-row" key={upgrade.id}>
                      <div><strong>{upgrade.label}</strong><span>{upgrade.description}</span><small>{duration(upgrade.hours)} · resulting weight {upgrade.resultingWeightKg === null ? "n/a" : mass(upgrade.resultingWeightKg, unit)}{upgrade.reason ? " · " + upgrade.reason : ""}</small></div>
                      <b>{money(upgrade.costCents)}</b>
                      <button disabled={busy || eventPending || !upgrade.available} title={upgrade.reason || ""} onClick={() => run("upgrade", { upgradeId: upgrade.id })}>{upgrade.installed ? "Installed" : "Install"}</button>
                    </article>
                  ))}
                  <h3>Sell project</h3>
                  <p>{game.ad ? (game.ad.askCents === undefined ? "Earlier listing: asking price was not recorded. Relist to set it." : `Listed at ${money(game.ad.askCents)} since Day ${game.ad.day}, ${clock(game.ad.hour)}.`) : `Suggested asking price ${money(game.guidePriceCents!)} from what you currently know.`} Buyers make their own offers; hidden defects may reduce them.</p>
                  {game.ad && <p>Existing offers stay fixed after repairs. Relist to withdraw them and invite a fresh valuation, or decline a single offer. New offers last {game.market.offerLifetimeHours} hours.</p>}
                  <div className="button-row">
                    <button disabled={busy || eventPending || !game.canAdvertise} onClick={() => run("advertise", { strategy: "market" })}>Advertise at market price</button>
                    {game.ad && <button disabled={busy || eventPending} onClick={() => run("relist")}>Relist for fresh offers</button>}
                    <button disabled={busy || eventPending || !game.canWait} onClick={() => run("wait")}>Wait for offers | {duration(game.market.waitHours)}</button>
                  </div>
                  {game.offers.map((offer) => (
                    <article className="offer" key={offer.id}>
                      <span>{offer.buyerName}</span>
                      <strong>{money(offer.amountCents)}</strong>
                      <span>Project result: {money(offer.projectProfitCents)}</span>
                      <small>Before living costs, interest and recovery bills. {offer.expiresAtHour === null ? "Earlier offer with no expiry; relist for current buyers." : offer.expired ? "Expired" : `Expires Day ${Math.floor(offer.expiresAtHour / 24) + 1}, ${clock(offer.expiresAtHour % 24)}.`}</small>
                      <ul>{offer.rationale.map((reason) => <li key={reason}>{reason}</li>)}</ul>
                      <small>Handover: {offer.handoverLocationName}.</small>
                      <button disabled={busy || eventPending || offer.expired} onClick={() => run("sell", { offerId: offer.id })}>Sell and hand over | {duration(game.market.saleHours)}</button>
                      <button disabled={busy || eventPending} onClick={() => run("decline-offer", { offerId: offer.id })}>Decline offer</button>
                    </article>
                  ))}
                </>
              ) : <p className="garage-note">Bring the camper to the Maple Junction garage before installing upgrades or advertising it for sale.</p>}
            </>
          )}

          <h3>Debt choice</h3>
          <div className="repay">
            <label>Payment G$ <input type="text" inputMode="decimal" value={repayAmount} onChange={(event) => setRepayAmount(event.target.value)} aria-describedby="payment-help" /></label>
            <button disabled={busy || eventPending || !paymentValid} onClick={() => run("repay", { amountCents: paymentCents })}>Pay selected amount</button>
            <button disabled={busy || eventPending || maximumPayment <= 0} onClick={() => run("repay", { amountCents: maximumPayment })}>Pay maximum {money(maximumPayment)}</button>
          </div>
          <p id="payment-help">{maximumPayment > 0 ? `Enter up to ${money(maximumPayment)}. A payment leaves less cash for your next project.` : "No affordable debt payment is available."}</p>
          <p>Principal {money(game.finances.loanPrincipalCents)} · Interest {money(game.finances.accruedInterestCents)} · Unpaid bills {money(game.finances.payablesCents)}. Daily loan interest: {game.daily.loanInterestBps / 100}%. Living costs: {money(game.daily.operatingCostCents)} plus {money(game.daily.awayParkingCents)} while your camper is away from home.</p>
          {game.canComplete && <button className="keeper" disabled={busy || eventPending} onClick={() => run("complete")}>Make This One Home</button>}
        </section>

        <section className={"panel log-panel " + (latest?.tone || "")}>
          <div className="panel-heading"><h2>Road log</h2><span>Saved revision {game.revision} | {game.contentVersion}</span></div>
          {game.eventLog.slice(-6).reverse().map((entry, index) => (
            <p className={index === 0 ? "latest" : ""} key={entry.day + "-" + entry.hour + "-" + index}>
              <b>Day {entry.day}, {clock(entry.hour)}</b> {entry.text}
            </p>
          ))}
          <details className="ledger"><summary>Earlier history ({Math.max(0, game.eventLog.length - 6)} entries)</summary>
            {game.eventLog.slice(0, -6).reverse().map((entry, index) => <p key={index}><b>Day {entry.day}, {clock(entry.hour)}</b> {entry.text}</p>)}
            <p>Entries retained by this save are shown. Older entries already removed by earlier builds cannot be recovered.</p>
          </details>
          <details className="ledger" open={finished}><summary>Completed sales ({game.saleCount})</summary>
            {game.soldCampers.map((sale) => <article key={sale.instanceId}><h3>{sale.name} · {sale.instanceId.slice(0, 8)}</h3><p>Proceeds {money(sale.proceedsCents)} − project costs {money(sale.projectCostCents)} = {money(sale.profitCents)} before campaign overhead.</p>{sale.expenses ? <ExpenseList expenses={sale.expenses}/> : <p>This earlier sale retained totals only.</p>}</article>)}
            <p>Living costs, interest and recovery bills are separate campaign overhead.</p>
          </details>
          <div className="diagnostics">
            <span>API/save connected</span>
            <span>{game.buildId}</span>
            <button className="text-button" onClick={reset} disabled={busy}>Restart this campaign</button>
          </div>
        </section>
      </main>
    </div>
  );
}

function GameDialog({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => { ref.current?.showModal(); }, []);
  return <dialog ref={ref} className="game-dialog" aria-labelledby="dialog-title" onCancel={(event) => { event.preventDefault(); onClose(); }}>
    <h2 id="dialog-title">{title}</h2>{children}
  </dialog>;
}

function ExpenseList({ expenses }: { expenses: Array<{ type: string; amountCents: number }> }) {
  const totals = new Map<string, number>();
  for (const expense of expenses) totals.set(expense.type, (totals.get(expense.type) || 0) + expense.amountCents);
  return <ul>{Array.from(totals, ([category, cents]) => <li key={category}>{category.charAt(0).toUpperCase() + category.slice(1)}: {money(cents)}</li>)}</ul>;
}

function actionPreview(game: GameState, type: string, payload: Record<string, unknown>): string {
  let text = "";
  let hours = 0;
  switch (type) {
    case "travel": {
      const route = game.routes.find((item) => item.id === payload.routeId)!;
      const tow = payload.mode === "tow";
      hours = tow ? route.towHours : route.soloHours;
      text = `${tow ? "Tow your camper" : "Drive Bluebird alone"} to ${route.destinationName}: ${money(tow ? route.towCostCents : route.soloCostCents)}, ${distance(route.distanceKm, game.player.distanceUnit)}. ${tow && route.currentWeightKg !== null ? `Current tow weight ${mass(route.currentWeightKg, game.player.distanceUnit)}; Bluebird limit ${mass(route.towLimitKg, game.player.distanceUnit)}. ` : ""}${!tow && game.camper ? `Your camper stays at ${game.camper.locationName}.` : "Unresolved tire problems can cause a breakdown. A brief check can miss faults."}`;
      break;
    }
    case "inspect": text = `Self-check ${game.camper!.hud.find((s) => s.id === payload.systemId)!.label}. No direct fee. Hidden faults may be missed.`; hours = game.inspection.selfHours; break;
    case "technician-inspect": text = `Full technician inspection of ${game.camper!.name}: ${money(game.inspection.technicianCostCents)}. Reveals existing hidden defects; repairs cost extra.`; hours = game.inspection.technicianHours; break;
    case "repair": {
      const repair = game.repairs.find((item) => item.id === payload.repairId)!;
      const diy = payload.method === "diy";
      text = `${repair.label} on ${game.camper!.name}: ${diy ? "your own work, no direct fee" : money(repair.technicianCostCents) + " for a technician"}. Repairs this known issue only.`;
      hours = diy ? repair.diyHours! : repair.technicianHours;
      break;
    }
    case "upgrade": { const upgrade = game.upgrades.find((item) => item.id === payload.upgradeId)!; text = `${upgrade.label}: ${money(upgrade.costCents)}. ${upgrade.description}`; hours = upgrade.hours; break; }
    case "set-tank": {
      const tank = payload.tankId as "fresh" | "grey" | "black";
      const target = payload.targetLitres as number;
      const current = game.camper!.tankLitres[tank];
      const cost = tank === "fresh" && target > current ? (target - current) * game.weightRules.freshWaterCentsPerLitre : tank === "fresh" ? 0 : game.weightRules.wasteDumpCostCents;
      text = `Change the ${tank} tank from ${current} L to ${target} L. Tow weight changes by ${target - current} kg. Direct cost ${money(cost)}.`;
      hours = game.weightRules.tankServiceHours;
      break;
    }
    case "set-loose-load": { const target = payload.targetKg as number; text = `Change loose camping load from ${mass(game.camper!.looseLoadKg, game.player.distanceUnit)} to ${mass(target, game.player.distanceUnit)}. Current tow weight becomes ${mass(game.camper!.weight.currentKg - game.camper!.looseLoadKg + target, game.player.distanceUnit)}.`; break; }
    case "buy-intel": text = `Buy the exact Day ${game.day + 1} ${game.player.locationName} demand and technician-price forecast from ${game.market.local.intel.contact} for ${money(game.market.local.intel.costCents)}.`; break;
    case "resolve-event": { const choice = game.pendingEvent!.choices.find((item) => item.id === payload.choiceId)!; text = `${choice.label}: ${choice.preview}${choice.cashCostCents > 0 ? ` Direct cost ${money(choice.cashCostCents)}.` : ""}${choice.recoveryPayableCents > 0 ? ` Recovery adds ${money(choice.recoveryPayableCents)} to debt.` : ""}`; hours = choice.hours; break; }
    case "work": text = `${game.work.employer}: ${game.work.task}. Receive ${money(game.work.grossCents)} when finished. This is a one-off daytime job.`; hours = game.work.hours; break;
    case "rest": text = "Rest until the next local jobs open. No wages; normal daily bills still apply."; hours = game.work.restHours; break;
    case "recover": text = `Recover you and the camper to Maple Junction. Add ${money(game.recoveryPayableCents)} to debt, with no cash required now. This bill is separate from project profit. No defects are repaired.`; hours = game.recoveryHours; break;
    case "repay": { const amount = payload.amountCents as number; text = `Pay exactly ${money(amount)} toward debt. Cash remaining ${money(game.finances.cashCents - amount)}; debt remaining ${money(game.finances.totalDebtCents - amount)}. Interest is paid first, then principal, then bills.`; break; }
    case "buy": { const listing = game.listings.find((item) => item.id === payload.listingId)!; text = `Buy ${listing.name}, vehicle reference ${listing.instanceId.slice(0, 8)}, for ${money(listing.askCents)}. Seller says: ${listing.sellerClaim} Hidden defects may remain. Dry ${mass(listing.dryWeightKg, game.player.distanceUnit)}; rating ${mass(listing.grossVehicleWeightRatingKg, game.player.distanceUnit)}. Cash remaining ${money(game.finances.cashCents - listing.askCents)}.`; break; }
    case "advertise": case "relist": text = `List ${game.camper!.name} at ${money(game.guidePriceCents!)} based on known condition. ${type === "relist" ? "Withdraw existing offers and request a fresh valuation." : "Buyers make their own offers after time advances."} No direct fee.`; break;
    case "wait": text = "Wait for another buyer. Normal bills apply if midnight passes."; hours = game.market.waitHours; break;
    case "decline-offer": text = "Decline this offer. The advertisement stays active; advance time to hear from another buyer. No fee or time cost."; break;
    case "sell": { const offer = game.offers.find((item) => item.id === payload.offerId)!; text = `Sell ${game.camper!.name} and all its installed upgrades for ${money(offer.amountCents)}. Project costs ${money(game.camper!.projectCostCents)}; project result ${money(offer.projectProfitCents)} before living costs, interest and recovery bills. Debt is not paid automatically.`; hours = game.market.saleHours; break; }
    case "complete": text = `Keep ${game.camper!.name} and finish this campaign. You have ${money(game.finances.cashCents)} cash and no debt. Further trading, work and travel end; your results remain available.`; break;
    default: throw new Error("No preview exists for this action.");
  }
  if (hours > 0) {
    const end = game.hour + hours;
    text += `\n\nTakes ${duration(hours)}. Day ${game.day}, ${clock(game.hour)} → Day ${game.day + Math.floor(end / 24)}, ${clock(end % 24)}.`;
    if (end >= 24) text += ` Midnight bills will settle. Living costs ${money(game.daily.operatingCostCents)}, plus ${money(game.daily.awayParkingCents)} if the camper is away from home; current daily interest ${money(game.daily.nextInterestCents)}.`;
  } else text += "\n\nNo time passes.";
  return text;
}

function Stat({ label, value, danger = false }: { label: string; value: string; danger?: boolean }) {
  return <div className={`stat stat-${label.toLowerCase()}${danger ? " danger" : ""}`}><span>{label}</span><strong>{value}</strong></div>;
}

function ClockStat({ day, hour }: { day: number; hour: number }) {
  const remaining = 24 - hour;
  return (
    <div className="clock-stat" aria-live="polite">
      <span>Day {day}</span>
      <strong>{clock(hour)}</strong>
      <small>{remaining}h to midnight</small>
    </div>
  );
}
