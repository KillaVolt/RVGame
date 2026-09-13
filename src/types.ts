export type HudState = "UNKNOWN" | "LOOKS_OK" | "WATCH" | "FAULT" | "TOWING_BLOCKED";

export interface MoneyState {
  cashCents: number;
  loanPrincipalCents: number;
  accruedInterestCents: number;
  payablesCents: number;
  totalDebtCents: number;
}

export interface HudSystem {
  id: string;
  label: string;
  state: HudState;
  evidence: string;
  selfReason: string | null;
}

export interface CamperView {
  instanceId: string;
  archetypeId: string;
  name: string;
  locationId: string;
  locationName: string;
  dream: boolean;
  tags: string[];
  towedKm: number;
  transportedKm: number;
  weight: {
    dryKg: number;
    tankKg: number;
    fullTankKg: number;
    upgradeKg: number;
    looseLoadKg: number;
    currentKg: number;
    allTanksFullKg: number;
    grossVehicleWeightRatingKg: number;
    remainingPayloadKg: number;
  };
  tankLitres: { fresh: number; grey: number; black: number };
  tankCapacitiesLitres: { fresh: number; grey: number; black: number };
  looseLoadKg: number;
  hud: HudSystem[];
  knownDefects: Array<{ id: string; systemId: string; severity: string; resolved: boolean }>;
  upgrades: string[];
  projectCostCents: number;
  projectExpenses: Array<{ type: string; amountCents: number }>;
}

export interface RouteView {
  id: string;
  destinationId: string;
  destinationName: string;
  distanceKm: number;
  towCostCents: number;
  soloCostCents: number;
  canTow: boolean;
  canSolo: boolean;
  towReason: string | null;
  towHours: number;
  soloHours: number;
  currentWeightKg: number | null;
  towLimitKg: number;
}

export interface MapRouteView {
  id: string;
  a: string;
  b: string;
  distanceKm: number;
  reachable: boolean;
}

export interface StartOption {
  id: string;
  name: string;
  tagline: string;
  description: string;
  equipment: string[];
  benefit: string;
}

export interface ListingView {
  id: string;
  instanceId: string;
  name: string;
  archetypeId: string;
  locationId: string;
  locationName: string;
  askCents: number;
  sellerClaim: string;
  expiresAtHour: number;
  dryWeightKg: number;
  grossVehicleWeightRatingKg: number;
  dream: boolean;
  knownIssues: string[];
  canBuy: boolean;
  buyReason: string | null;
}

export interface OfferView {
  id: string;
  buyerName: string;
  amountCents: number;
  projectProfitCents: number;
  expiresAtHour: number | null;
  expired: boolean;
  rationale: string[];
  handoverLocationName: string;
}

export interface RepairView {
  id: string;
  label: string;
  systemId: string;
  systemName: string;
  serviceNote: string;
  technicianCostCents: number;
  technicianHours: number;
  technicianAvailable: boolean;
  technicianReason: string | null;
  diyAllowed: boolean;
  diyHours: number | null;
  diyAvailable: boolean;
  diyReason: string | null;
}

export interface UpgradeView {
  id: string;
  label: string;
  costCents: number;
  hours: number;
  available: boolean;
  reason: string | null;
  installed: boolean;
  description: string;
  valueCents: number;
  weightKg: number;
  appealTags: string[];
  resultingWeightKg: number | null;
}

export interface MarketDemand {
  tag: string;
  label: string;
  bps: number;
  level: "strong" | "steady" | "soft";
}

export interface PendingEvent {
  instanceId: string;
  eventId: string;
  day: number;
  hour: number;
  title: string;
  text: string;
  choices: Array<{
    id: string;
    label: string;
    preview: string;
    hours: number;
    cashCostCents: number;
    recoveryPayableCents: number;
    available: boolean;
    reason: string | null;
  }>;
}

export interface GameState {
  buildId: string;
  contentVersion: string;
  campaignId: string;
  revision: number;
  status: "active" | "completed";
  day: number;
  hour: number;
  player: { locationId: string; locationName: string; distanceUnit: "km" | "mi"; approachId: string; approachName: string; tools: string[]; perks: string[] };
  inspection: { selfHours: number; technicianCostCents: number; technicianHours: number; technicianName: string; selfAvailable: boolean; selfReason: string | null; technicianReason: string | null };
  finances: MoneyState;
  daily: { operatingCostCents: number; awayParkingCents: number; loanInterestBps: number; nextCostCents: number; nextInterestCents: number };
  goal: { minimumReserveCents: number; unlockAfterSales: number; requirements: Array<{ label: string; met: boolean }> };
  ad: { day: number; hour: number; askCents?: number } | null;
  guidePriceCents: number | null;
  soldCampers: Array<{ instanceId: string; name: string; proceedsCents: number; projectCostCents: number; profitCents: number; expenses?: Array<{ type: string; amountCents: number }> }>;
  towVehicle: { name: string; locationId: string; odometerKm: number; maxTrailerWeightKg: number };
  objective: string;
  camper: CamperView | null;
  routes: RouteView[];
  listings: ListingView[];
  offers: OfferView[];
  repairs: RepairView[];
  upgrades: UpgradeView[];
  mapLocations: Array<{ id: string; name: string; x: number; y: number; current: boolean; camper: boolean }>;
  mapRoutes: MapRouteView[];
  recoveryPayableCents: number;
  recoveryHours: number;
  work: { grossCents: number; hours: number; employer: string; task: string; available: boolean; startHour: number; lastStartHour: number; restHours: number };
  market: {
    waitHours: number;
    saleHours: number;
    offerLifetimeHours: number;
    listingLifetimeHours: number;
    local: {
      phase: string;
      demand: MarketDemand[];
      technician: { name: string; available: boolean; priceBps: number; currentPriceBps: number };
      water: { freshFill: boolean; wasteDump: boolean };
      intel: { contact: string; costCents: number; available: boolean; reason: string | null };
    };
    history: Array<{ day: number; phase: string; demand: MarketDemand[]; technicianPriceBps: number; listingCount: number }>;
    intelReports: Array<{ id: string; purchasedDay: number; locationId: string; locationName: string; forecastDay: number; tag: string; tagLabel: string; demandBps: number; text: string }>;
  };
  weightRules: { looseLoadStepKg: number; maximumLooseLoadKg: number; tankServiceHours: number; freshWaterCentsPerLitre: number; wasteDumpCostCents: number };
  pendingEvent: PendingEvent | null;
  technicianCreditCents: number;
  atGarageWithCamper: boolean;
  canAdvertise: boolean;
  canWait: boolean;
  canRecover: boolean;
  canComplete: boolean;
  saleCount: number;
  eventLog: Array<{ day: number; hour: number; text: string; tone: string }>;
}

export interface ApiResponse {
  ok: boolean;
  state?: GameState | null;
  setup?: StartOption[];
  community?: CommunitySummary;
  result?: { summary: string };
  duplicate?: boolean;
  resetRequired?: { message: string };
  error?: { code: string; message: string };
}

export interface CommunitySummary {
  ownerExcluded: boolean;
  averageRating: number | null;
  ratingCount: number;
  userRating: number | null;
}

export interface Bootstrap {
  state: GameState | null;
  setup: StartOption[];
  resetRequired: { message: string } | null;
}
