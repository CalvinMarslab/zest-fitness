import Foundation

/// Sends completed workouts to the Zest Fitness Laravel API.
struct APIService {

    static func postWorkout(_ workout: CompletedWorkout,
                            resultValue: String,
                            notes: String?,
                            token: String) async throws {
        guard let url = URL(string: "\(AuthManager.baseURL)/api/workouts") else {
            throw APIError.invalidURL
        }

        var req = URLRequest(url: url)
        req.httpMethod = "POST"
        req.setValue("application/json",   forHTTPHeaderField: "Content-Type")
        req.setValue("application/json",   forHTTPHeaderField: "Accept")
        req.setValue("Bearer \(token)",    forHTTPHeaderField: "Authorization")

        let iso = ISO8601DateFormatter()
        var body: [String: Any] = [
            "exercise":    workout.exerciseName,
            "value":       resultValue,
            "duration":    workout.durationSecs,
            "calories":    workout.activeCalories,
            "heart_rate":  workout.avgHeartRate,
            "distance":    workout.distanceMetres,
            "recorded_at": iso.string(from: workout.startDate),
        ]
        if let notes, !notes.isEmpty { body["notes"] = notes }

        req.httpBody = try JSONSerialization.data(withJSONObject: body)

        let (_, response) = try await URLSession.shared.data(for: req)
        guard let http = response as? HTTPURLResponse, http.statusCode == 201 else {
            throw APIError.serverError
        }
    }

    static func fetchWorkouts(token: String) async throws -> [WorkoutRecord] {
        guard let url = URL(string: "\(AuthManager.baseURL)/api/workouts") else {
            throw APIError.invalidURL
        }

        var req = URLRequest(url: url)
        req.setValue("Bearer \(token)",  forHTTPHeaderField: "Authorization")
        req.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: req)
        return try JSONDecoder().decode([WorkoutRecord].self, from: data)
    }

    enum APIError: LocalizedError {
        case invalidURL
        case serverError

        var errorDescription: String? {
            switch self {
            case .invalidURL:   return "Invalid server URL."
            case .serverError:  return "Server returned an error. Check your connection."
            }
        }
    }
}

struct WorkoutRecord: Decodable, Identifiable {
    let id:          Int
    let result_date: String
    let exercise:    String
    let value:       String
    let notes:       String?
    let created_at:  String
}
