import Foundation

/// Manages API token storage and login/logout state.
@MainActor
class AuthManager: ObservableObject {
    @Published var isLoggedIn: Bool = false
    @Published var errorMessage: String?

    private let tokenKey = "zest_api_token"
    // ↓ Change to your server address when testing on device
    static let baseURL = "http://localhost:8000"

    var token: String? {
        get { UserDefaults.standard.string(forKey: tokenKey) }
        set {
            UserDefaults.standard.set(newValue, forKey: tokenKey)
            isLoggedIn = newValue != nil
        }
    }

    init() {
        isLoggedIn = token != nil
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    func login(email: String, password: String) async {
        errorMessage = nil
        guard let url = URL(string: "\(AuthManager.baseURL)/api/auth/token") else { return }

        var req = URLRequest(url: url)
        req.httpMethod = "POST"
        req.setValue("application/json", forHTTPHeaderField: "Content-Type")
        req.setValue("application/json", forHTTPHeaderField: "Accept")

        let body: [String: String] = [
            "email": email,
            "password": password,
            "device_name": "Apple Watch",
        ]
        req.httpBody = try? JSONEncoder().encode(body)

        do {
            let (data, _) = try await URLSession.shared.data(for: req)
            let response  = try JSONDecoder().decode(TokenResponse.self, from: data)
            token = response.token
        } catch {
            errorMessage = "Login failed. Check your credentials."
        }
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    func logout() async {
        guard let t = token,
              let url = URL(string: "\(AuthManager.baseURL)/api/auth/token") else {
            token = nil
            return
        }

        var req = URLRequest(url: url)
        req.httpMethod = "DELETE"
        req.setValue("Bearer \(t)", forHTTPHeaderField: "Authorization")
        req.setValue("application/json", forHTTPHeaderField: "Accept")

        _ = try? await URLSession.shared.data(for: req)
        token = nil
    }
}

private struct TokenResponse: Decodable { let token: String }
