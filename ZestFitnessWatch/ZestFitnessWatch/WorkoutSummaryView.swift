import SwiftUI

/// Post-workout screen: user confirms the result value before syncing.
struct WorkoutSummaryView: View {
    let workout: CompletedWorkout

    @EnvironmentObject var auth: AuthManager
    @Environment(\.dismiss) private var dismiss

    @State private var resultValue: String = ""
    @State private var notes:       String = ""
    @State private var syncing = false
    @State private var synced  = false
    @State private var errorMsg: String?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                Text(workout.exerciseName)
                    .font(.headline)
                    .foregroundStyle(.orange)

                // Stats summary
                statsRow("Duration",  value: formatDuration(workout.durationSecs))
                statsRow("Calories",  value: "\(workout.activeCalories) kcal")
                if workout.avgHeartRate > 0 {
                    statsRow("Avg HR", value: "\(workout.avgHeartRate) bpm")
                }
                if workout.distanceMetres > 0 {
                    let km = Double(workout.distanceMetres) / 1000
                    statsRow("Distance", value: String(format: "%.2f km", km))
                }

                Divider()

                // Editable result
                VStack(alignment: .leading, spacing: 4) {
                    Text("Result")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                    TextField("e.g. 120 kg, 22:30", text: $resultValue)
                        .onAppear { resultValue = workout.defaultValue }
                }

                VStack(alignment: .leading, spacing: 4) {
                    Text("Notes (optional)")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                    TextField("PR? Felt great?", text: $notes)
                }

                if let err = errorMsg {
                    Text(err)
                        .font(.caption)
                        .foregroundStyle(.red)
                }

                if synced {
                    Label("Saved to Results Board!", systemImage: "checkmark.circle.fill")
                        .font(.caption)
                        .foregroundStyle(.green)
                }

                Button {
                    Task { await sync() }
                } label: {
                    if syncing {
                        ProgressView()
                    } else {
                        Text(synced ? "Done" : "Log Result")
                            .frame(maxWidth: .infinity)
                    }
                }
                .buttonStyle(.borderedProminent)
                .tint(synced ? .green : .orange)
                .disabled(resultValue.isEmpty || syncing)
            }
            .padding()
        }
        .navigationTitle("Summary")
        .navigationBarBackButtonHidden(syncing)
    }

    // ── Sync ──────────────────────────────────────────────────────────────────

    private func sync() async {
        guard let token = auth.token else { return }
        syncing  = true
        errorMsg = nil

        do {
            try await APIService.postWorkout(
                workout,
                resultValue: resultValue,
                notes: notes.isEmpty ? nil : notes,
                token: token
            )
            synced = true
        } catch {
            errorMsg = error.localizedDescription
        }
        syncing = false
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private func statsRow(_ label: String, value: String) -> some View {
        HStack {
            Text(label).foregroundStyle(.secondary).font(.caption)
            Spacer()
            Text(value).font(.caption.bold())
        }
    }

    private func formatDuration(_ secs: Int) -> String {
        let m = secs / 60
        let s = secs % 60
        return String(format: "%d:%02d", m, s)
    }
}
