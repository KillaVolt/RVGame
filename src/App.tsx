import { useEffect, useState } from "react";
import { command, loadGame, newGame, resetGame } from "./api";
import type { GameState, HudState, StartOption } from "./types";

const money = (cents: number) =>
  "G$" + (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });

const distance = (km: number, unit: "km" | "mi") =>
  unit === "km" ? km + " km" : (km * 0.621371).toFixed(1) + " mi";

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
  const [selectedRouteId, setSelectedRouteId] = useState("");
  const [repayAmount, setRepayAmount] = useState(100);

  useEffect(() => {
    loadGame()
      .then((bootstrap) => {
        setGame(bootstrap.state);
        setSetup(bootstrap.setup);
      })
      .catch((cause: Error) => setError(cause.message))
      .finally(() => setLoaded(true));
  }, []);

  async function run(type: string, payload: Record<string, unknown> = {}) {
    if (!game || busy) return;
    setBusy(true);
    setError("");
    try {
      setGame(await command(game, type, payload));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "The action failed.");
    } finally {
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
    if (!confirm("Delete this local playtest campaign and start over?")) return;
    setBusy(true);
    try {
      await resetGame();
      setGame(null);
      setLoadoutId("");
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
          <button onClick={start} disabled={busy || !loadoutId}>Take ownership of the fixer-upper</button>
          {error && <p className="error" role="alert">{error}</p>}
        </div>
      </main>
    );
  }

  const unit = game.player.distanceUnit;
  const latest = game.eventLog.at(-1);
  const selectedRoute = game.routes.find((route) => route.id === selectedRouteId) || game.routes[0];
  const locations = new Map(game.mapLocations.map((place) => [place.id, place]));
  const hoursUntilMidnight = 24 - game.hour;

  return (
    <div className="app-shell">
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
      </header>

      {error && <div className="error-bar" role="alert">Save/API error: {error}</div>}
      {game.status === "completed" && (
        <div className="complete-banner">
          <strong>You made this one home.</strong> The debt is clear and the Sunbeam is yours to keep.
        </div>
      )}

      <main className="dashboard">
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
                  disabled={!route}
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
                <button disabled={busy || !selectedRoute.canTow} title={selectedRoute.towReason || ""} onClick={() => run("travel", { routeId: selectedRoute.id, mode: "tow" })}>
                  Tow {money(selectedRoute.towCostCents)} | {duration(selectedRoute.towHours)}
                </button>
                <button disabled={busy || !selectedRoute.canSolo} onClick={() => run("travel", { routeId: selectedRoute.id, mode: "solo" })}>
                  Drive {money(selectedRoute.soloCostCents)} | {duration(selectedRoute.soloHours)}
                </button>
              </div>
            ) : <p className="muted">No road leaves this location.</p>}
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
                  <div className={"hud-item state-" + system.state.toLowerCase()} key={system.id} title={system.evidence}>
                    <span>{system.label}</span>
                    <strong>{stateLabel[system.state]}</strong>
                  </div>
                ))}
              </div>
              <div className="distance-strip">
                <span>Bluebird odometer <b>{distance(game.towVehicle.odometerKm, unit)}</b></span>
                <span>Camper towed <b>{distance(game.camper.towedKm, unit)}</b></span>
                {game.camper.transportedKm > 0 && <span>Recovered <b>{distance(game.camper.transportedKm, unit)}</b></span>}
                <span>Project cost <b>{money(game.camper.projectCostCents)}</b></span>
                <span>Approach <b>{game.player.approachName}</b></span>
              </div>
            </>
          ) : (
            <p className="empty">The garage, Bluebird, cash, debt, and your ability to work or buy remain available.</p>
          )}
        </section>

        <section className="panel market-panel" aria-labelledby="market-title">
          <div className="panel-heading"><h2 id="market-title">Local and market</h2><span>Compact opportunities</span></div>
          <h3>Campers for sale</h3>
          {game.listings.length === 0 && <p className="muted">No local listings yet. Finish or sell the current project.</p>}
          {game.listings.map((listing) => (
            <article className="dense-row" key={listing.id}>
              <div>
                <strong>{listing.name}{listing.dream ? " - dream target" : ""}</strong>
                <span>{listing.locationName} | {listing.knownIssues.join(", ") || "seller reports no issue"}</span>
              </div>
              <b>{money(listing.askCents)}</b>
              <button disabled={busy || !listing.canBuy} title={listing.buyReason || ""} onClick={() => run("buy", { listingId: listing.id })}>Buy</button>
            </article>
          ))}
          <h3>Work and recovery</h3>
          <div className="button-row">
            <button disabled={busy} onClick={() => run("work")}>Work shift +{money(game.work.grossCents)} | {duration(game.work.hours)}</button>
            <button disabled={busy || !game.canRecover} onClick={() => run("recover")}>Recovery to garage +{money(game.recoveryPayableCents)} payable | {duration(game.recoveryHours)}</button>
          </div>
        </section>

        <section className="panel actions-panel" aria-labelledby="actions-title">
          <div className="panel-heading"><h2 id="actions-title">Project actions</h2><span>Preview before commit</span></div>
          <div className="time-context" aria-label={`Current time Day ${game.day}, ${clock(game.hour)}`}>
            <span>Current time</span>
            <strong>Day {game.day} | {clock(game.hour)}</strong>
            <small>{hoursUntilMidnight} {hoursUntilMidnight === 1 ? "hour" : "hours"} until daily costs and loan interest settle</small>
          </div>
          {game.camper && (
            <>
              <h3>Inspect systems</h3>
              {game.inspection.selfAvailable ? (
                <div className="compact-buttons">
                  {game.camper.hud.map((system) => (
                    <button key={system.id} disabled={busy} onClick={() => run("inspect", { systemId: system.id })}>
                      Self-check {system.label} | Free / {duration(game.inspection.selfHours)}
                    </button>
                  ))}
                </div>
              ) : (
                <p className="muted">{game.inspection.selfReason}</p>
              )}
              <div className="button-row">
                <button disabled={busy} onClick={() => run("technician-inspect")}>
                  Call technician | {money(game.inspection.technicianCostCents)} / {duration(game.inspection.technicianHours)}
                </button>
              </div>
              <h3>Known issues</h3>
              {game.camper.knownDefects.length === 0 && <p className="muted">No known unresolved defects.</p>}
              {game.repairs.filter((repair) => repair.diyReason !== "No matching known issue." || repair.technicianReason !== "No matching known issue.").map((repair) => (
                <article className="dense-row" key={repair.id}>
                  <div><strong>{repair.label}</strong><span>{repair.systemId} | choose time or money</span></div>
                  <span>{repair.diyAllowed ? `DIY ${duration(repair.diyHours || 0)}` : "Technician only"}</span>
                  <div className="button-row">
                    {repair.diyAllowed && <button disabled={busy || !repair.diyAvailable} title={repair.diyReason || ""} onClick={() => run("repair", { repairId: repair.id, method: "diy" })}>DIY free</button>}
                    <button disabled={busy || !repair.technicianAvailable} title={repair.technicianReason || ""} onClick={() => run("repair", { repairId: repair.id, method: "technician" })}>Tech {money(repair.technicianCostCents)} | {duration(repair.technicianHours)}</button>
                  </div>
                </article>
              ))}
              {game.repairs.every((repair) => repair.diyReason === "No matching known issue." && repair.technicianReason === "No matching known issue.") && (
                <p className="muted">Self-check systems for obvious issues or pay a technician for a deeper inspection.</p>
              )}
              {game.atGarageWithCamper ? (
                <>
                  <h3>Garage improvements</h3>
                  {game.upgrades.map((upgrade) => (
                    <article className="dense-row" key={upgrade.id}>
                      <div><strong>{upgrade.label}</strong><span>Optional garage upgrade | {duration(upgrade.hours)}</span></div>
                      <b>{money(upgrade.costCents)}</b>
                      <button disabled={busy || !upgrade.available} title={upgrade.reason || ""} onClick={() => run("upgrade", { upgradeId: upgrade.id })}>Install</button>
                    </article>
                  ))}
                  <h3>Sell project</h3>
                  <div className="button-row">
                    <button disabled={busy || !game.canAdvertise} onClick={() => run("advertise", { strategy: "market" })}>Advertise at market price</button>
                    <button disabled={busy || !game.canWait} onClick={() => run("wait")}>Wait for offers | {duration(game.market.waitHours)}</button>
                  </div>
                  {game.offers.map((offer) => (
                    <article className="offer" key={offer.id}>
                      <span>{offer.buyerName}</span>
                      <strong>{money(offer.amountCents)}</strong>
                      <span>Project result: {money(offer.projectProfitCents)}</span>
                      <button disabled={busy} onClick={() => run("sell", { offerId: offer.id })}>Sell and hand over | {duration(game.market.saleHours)}</button>
                    </article>
                  ))}
                </>
              ) : <p className="garage-note">Bring the camper to the Maple Junction garage before installing upgrades or advertising it for sale.</p>}
            </>
          )}

          <h3>Debt choice</h3>
          <div className="repay">
            <label>Payment G$ <input type="number" min="1" step="10" value={repayAmount} onChange={(event) => setRepayAmount(Number(event.target.value))} /></label>
            <button disabled={busy || game.finances.totalDebtCents === 0} onClick={() => run("repay", { amountCents: Math.round(repayAmount * 100) })}>Pay selected amount</button>
            <button disabled={busy || game.finances.totalDebtCents === 0} onClick={() => run("repay", { amountCents: game.finances.totalDebtCents })}>Pay maximum possible</button>
          </div>
          {game.canComplete && <button className="keeper" disabled={busy} onClick={() => run("complete")}>Make This One Home</button>}
        </section>

        <section className={"panel log-panel " + (latest?.tone || "")} aria-live="polite">
          <div className="panel-heading"><h2>Road log</h2><span>Saved revision {game.revision} | {game.contentVersion}</span></div>
          {game.eventLog.slice(-6).reverse().map((entry, index) => (
            <p className={index === 0 ? "latest" : ""} key={entry.day + "-" + entry.hour + "-" + index}>
              <b>Day {entry.day}, {clock(entry.hour)}</b> {entry.text}
            </p>
          ))}
          <div className="diagnostics">
            <span>API/save connected</span>
            <span>{game.buildId}</span>
            <button className="text-button" onClick={reset}>Reset local campaign</button>
          </div>
        </section>
      </main>
    </div>
  );
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
