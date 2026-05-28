import SwiftUI

/// Shows the user's recent workout results fetched from the server.
struct HistoryView: View {
    @EnvironmentObject var auth: AuthManager
    @State private var records:  [WorkoutRecord] = []
    @State private var loading = false
    @State private var errorMsg: String?

    var body: some View {
        NavigationStack {
            Group {
                if loading {
                    ProgressView("Loading…")
                } else if records.isEmpty {
                    Text("No workouts yet")
                        .foregroundStyle(.secondary)
                } else {
                    List(records) { record in
                        VStack(alignment: .leading, spacing: 4) {
                            Text(record.exercise).font(.headline)
                            Text(record.value)
                                .font(.subheadline)
                                .foregroundStyle(.orange)
                            Text(record.result_date)
                                .font(.caption)
                                .foregroundStyle(.secondary)
                            if let notes = record.notes, !notes.isEmpty {
                                Text(notes)
                                    .font(.caption2)
                                    .foregroundStyle(.secondary)
                            }
                        }
                    }
                }
            }
            .navigationTitle("History")
        }
        .task { await load() }
        .refreshable { await load() }
    }

    private func load() async {
        guard let token = auth.token else { return }
        loading  = true
        errorMsg = nil

        do {
            records = try await APIService.fetchWorkouts(token: token)
        } catch {
            errorMsg = error.localizedDescription
        }
        loading = false
    }
}
