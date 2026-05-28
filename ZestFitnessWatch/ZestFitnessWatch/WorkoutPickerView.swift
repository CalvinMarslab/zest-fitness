import SwiftUI
import HealthKit

/// Lets the user pick a workout type then start tracking.
struct WorkoutPickerView: View {

    private let workoutTypes: [(name: String, type: HKWorkoutActivityType, icon: String)] = [
        ("Strength",    .traditionalStrengthTraining, "dumbbell"),
        ("CrossFit",    .crossTraining,               "bolt.fill"),
        ("Run",         .running,                     "figure.run"),
        ("Cycle",       .cycling,                     "figure.outdoor.cycle"),
        ("HIIT",        .highIntensityIntervalTraining, "flame.fill"),
        ("Row",         .rowing,                      "oar.2.crossed"),
        ("Swim",        .swimming,                    "drop.fill"),
        ("Yoga",        .yoga,                        "figure.mind.and.body"),
    ]

    @State private var selected: HKWorkoutActivityType?
    @State private var navigateToActive = false

    var body: some View {
        NavigationStack {
            List(workoutTypes, id: \.name) { item in
                Button {
                    selected = item.type
                    navigateToActive = true
                } label: {
                    Label(item.name, systemImage: item.icon)
                        .foregroundStyle(.primary)
                }
            }
            .navigationTitle("Start Workout")
            .navigationDestination(isPresented: $navigateToActive) {
                if let type = selected {
                    ActiveWorkoutView(activityType: type)
                }
            }
        }
        .task { await requestHealthKit() }
    }

    private func requestHealthKit() async {
        let manager = WorkoutManager()
        await manager.requestAuthorization()
    }
}
