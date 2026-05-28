import SwiftUI

struct SettingsView: View {
    @EnvironmentObject var auth: AuthManager
    @State private var loggingOut = false

    var body: some View {
        NavigationStack {
            List {
                Section("Account") {
                    Button(role: .destructive) {
                        Task {
                            loggingOut = true
                            await auth.logout()
                            loggingOut = false
                        }
                    } label: {
                        if loggingOut {
                            ProgressView()
                        } else {
                            Label("Log Out", systemImage: "rectangle.portrait.and.arrow.right")
                        }
                    }
                }

                Section("Server") {
                    LabeledContent("URL", value: AuthManager.baseURL)
                        .font(.caption)
                }
            }
            .navigationTitle("Settings")
        }
    }
}
