import SwiftUI

struct ContentView: View {
    @EnvironmentObject var auth: AuthManager

    var body: some View {
        TabView {
            WorkoutPickerView()
                .tabItem { Label("Workout", systemImage: "figure.strengthtraining.traditional") }

            HistoryView()
                .tabItem { Label("History", systemImage: "list.bullet") }

            SettingsView()
                .tabItem { Label("Settings", systemImage: "gear") }
        }
    }
}
