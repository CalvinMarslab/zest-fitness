import Foundation
import HealthKit
import Combine

/// Manages the active HKWorkoutSession and streams live metrics.
@MainActor
class WorkoutManager: NSObject, ObservableObject {

    // ── Published state ───────────────────────────────────────────────────────
    @Published var isRunning         = false
    @Published var elapsedSeconds    = 0
    @Published var activeCalories    = 0
    @Published var heartRate         = 0        // bpm
    @Published var distanceMetres    = 0        // for runs/cycles
    @Published var errorMessage: String?

    // Workout type chosen by user
    var selectedType: HKWorkoutActivityType = .traditionalStrengthTraining

    // ── Private ───────────────────────────────────────────────────────────────
    private let healthStore     = HKHealthStore()
    private var session:          HKWorkoutSession?
    private var builder:          HKLiveWorkoutBuilder?
    private var timer:            AnyCancellable?
    private var startDate:        Date?

    // ── HealthKit permission request ──────────────────────────────────────────

    func requestAuthorization() async {
        let types: Set<HKSampleType> = [
            HKQuantityType(.activeEnergyBurned),
            HKQuantityType(.heartRate),
            HKQuantityType(.distanceWalkingRunning),
            HKQuantityType(.distanceCycling),
        ]
        try? await healthStore.requestAuthorization(toShare: types, read: types)
    }

    // ── Start workout ─────────────────────────────────────────────────────────

    func start(type: HKWorkoutActivityType) {
        selectedType = type

        let config = HKWorkoutConfiguration()
        config.activityType  = type
        config.locationType  = .unknown

        do {
            session = try HKWorkoutSession(healthStore: healthStore, configuration: config)
            builder = session?.associatedWorkoutBuilder()
        } catch {
            errorMessage = "Could not start workout session."
            return
        }

        builder?.dataSource = HKLiveWorkoutDataSource(
            healthStore: healthStore,
            workoutConfiguration: config
        )

        session?.delegate = self
        builder?.delegate = self

        startDate = Date()
        session?.startActivity(with: startDate!)
        builder?.beginCollection(withStart: startDate!) { _, _ in }

        isRunning = true
        startTimer()
    }

    // ── Stop workout ──────────────────────────────────────────────────────────

    /// Returns a `CompletedWorkout` with all stats collected.
    func stop() async -> CompletedWorkout? {
        guard isRunning else { return nil }

        session?.end()
        timer?.cancel()
        isRunning = false

        await builder?.endCollection(withEnd: Date())
        await builder?.finishWorkout()

        let duration = elapsedSeconds
        let cals     = activeCalories
        let hr       = heartRate
        let dist     = distanceMetres

        return CompletedWorkout(
            activityType:  selectedType,
            durationSecs:  duration,
            activeCalories: cals,
            avgHeartRate:  hr,
            distanceMetres: dist,
            startDate:     startDate ?? Date()
        )
    }

    // ── Timer ─────────────────────────────────────────────────────────────────

    private func startTimer() {
        elapsedSeconds = 0
        timer = Timer.publish(every: 1, on: .main, in: .common)
            .autoconnect()
            .sink { [weak self] _ in
                self?.elapsedSeconds += 1
            }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    func formattedElapsed() -> String {
        let h = elapsedSeconds / 3600
        let m = (elapsedSeconds % 3600) / 60
        let s = elapsedSeconds % 60
        return h > 0
            ? String(format: "%d:%02d:%02d", h, m, s)
            : String(format: "%02d:%02d", m, s)
    }
}

// ── HKWorkoutSessionDelegate ──────────────────────────────────────────────────

extension WorkoutManager: HKWorkoutSessionDelegate {
    nonisolated func workoutSession(
        _ workoutSession: HKWorkoutSession,
        didChangeTo toState: HKWorkoutSessionState,
        from fromState: HKWorkoutSessionState,
        date: Date
    ) {}

    nonisolated func workoutSession(
        _ workoutSession: HKWorkoutSession,
        didFailWithError error: Error
    ) {
        Task { @MainActor in self.errorMessage = error.localizedDescription }
    }
}

// ── HKLiveWorkoutBuilderDelegate ──────────────────────────────────────────────

extension WorkoutManager: HKLiveWorkoutBuilderDelegate {
    nonisolated func workoutBuilderDidCollectEvent(_ workoutBuilder: HKLiveWorkoutBuilder) {}

    nonisolated func workoutBuilder(
        _ workoutBuilder: HKLiveWorkoutBuilder,
        didCollectDataOf collectedTypes: Set<HKSampleType>
    ) {
        Task { @MainActor in
            for type in collectedTypes {
                guard let qt = type as? HKQuantityType else { continue }
                let stats = workoutBuilder.statistics(for: qt)

                switch qt {
                case HKQuantityType(.activeEnergyBurned):
                    self.activeCalories = Int(stats?.sumQuantity()?.doubleValue(for: .kilocalorie()) ?? 0)
                case HKQuantityType(.heartRate):
                    let bpm = stats?.mostRecentQuantity()?.doubleValue(
                        for: HKUnit.count().unitDivided(by: .minute())
                    ) ?? 0
                    self.heartRate = Int(bpm)
                case HKQuantityType(.distanceWalkingRunning),
                     HKQuantityType(.distanceCycling):
                    self.distanceMetres = Int(stats?.sumQuantity()?.doubleValue(for: .meter()) ?? 0)
                default:
                    break
                }
            }
        }
    }
}

// ── CompletedWorkout ──────────────────────────────────────────────────────────

struct CompletedWorkout {
    let activityType:   HKWorkoutActivityType
    let durationSecs:   Int
    let activeCalories: Int
    let avgHeartRate:   Int
    let distanceMetres: Int
    let startDate:      Date

    var exerciseName: String { activityType.displayName }

    /// Format a human-readable result string (user can override in UI)
    var defaultValue: String {
        switch activityType {
        case .running, .cycling, .swimming:
            if distanceMetres > 0 {
                let km = Double(distanceMetres) / 1000
                return String(format: "%.2f km", km)
            }
            let m = durationSecs / 60
            let s = durationSecs % 60
            return String(format: "%d:%02d", m, s)
        default:
            return "\(activeCalories) kcal"
        }
    }
}

extension HKWorkoutActivityType {
    var displayName: String {
        switch self {
        case .traditionalStrengthTraining: return "Strength Training"
        case .running:                     return "Run"
        case .cycling:                     return "Cycle"
        case .swimming:                    return "Swim"
        case .highIntensityIntervalTraining: return "HIIT"
        case .crossTraining:               return "CrossFit"
        case .functionalStrengthTraining:  return "Functional Strength"
        case .rowing:                      return "Row"
        case .yoga:                        return "Yoga"
        default:                           return "Workout"
        }
    }
}
