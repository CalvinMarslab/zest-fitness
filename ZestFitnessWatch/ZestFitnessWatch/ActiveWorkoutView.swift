import SwiftUI
import HealthKit

/// Live workout screen — shows timer, HR, calories. Stop → log result.
struct ActiveWorkoutView: View {
    let activityType: HKWorkoutActivityType

    @EnvironmentObject var auth: AuthManager
    @StateObject private var manager = WorkoutManager()
    @State private var showSummary   = false
    @State private var completed: CompletedWorkout?

    var body: some View {
        Group {
            if showSummary, let done = completed {
                WorkoutSummaryView(workout: done)
                    .environmentObject(auth)
            } else {
                liveView
            }
        }
        .onAppear { manager.start(type: activityType) }
    }

    // ── Live metrics ──────────────────────────────────────────────────────────

    private var liveView: some View {
        VStack(spacing: 8) {
            // Timer
            Text(manager.formattedElapsed())
                .font(.system(size: 32, weight: .bold, design: .monospaced))
                .foregroundStyle(.orange)

            Divider()

            HStack(spacing: 16) {
                stat(label: "BPM",  value: "\(manager.heartRate)",     color: .red)
                stat(label: "KCAL", value: "\(manager.activeCalories)", color: .yellow)
            }

            if manager.distanceMetres > 0 {
                let km = Double(manager.distanceMetres) / 1000
                stat(label: "KM", value: String(format: "%.2f", km), color: .green)
            }

            Spacer()

            Button(role: .destructive) {
                Task {
                    completed  = await manager.stop()
                    showSummary = true
                }
            } label: {
                Label("Stop", systemImage: "stop.fill")
            }
            .buttonStyle(.borderedProminent)
            .tint(.red)
        }
        .padding()
        .navigationTitle(activityType.displayName)
        .navigationBarBackButtonHidden(manager.isRunning)
    }

    private func stat(label: String, value: String, color: Color) -> some View {
        VStack(spacing: 2) {
            Text(value)
                .font(.title3.bold())
                .foregroundStyle(color)
            Text(label)
                .font(.caption2)
                .foregroundStyle(.secondary)
        }
    }
}
