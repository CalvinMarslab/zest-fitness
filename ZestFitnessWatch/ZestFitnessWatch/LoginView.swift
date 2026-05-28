import SwiftUI

struct LoginView: View {
    @EnvironmentObject var auth: AuthManager
    @State private var email    = ""
    @State private var password = ""
    @State private var loading  = false

    var body: some View {
        ScrollView {
            VStack(spacing: 12) {
                Text("Zest Fitness")
                    .font(.headline)
                    .foregroundStyle(.orange)

                TextField("Email", text: $email)
                    .textContentType(.emailAddress)
                    .autocapitalization(.none)

                SecureField("Password", text: $password)
                    .textContentType(.password)

                if let err = auth.errorMessage {
                    Text(err)
                        .font(.caption)
                        .foregroundStyle(.red)
                        .multilineTextAlignment(.center)
                }

                Button {
                    Task {
                        loading = true
                        await auth.login(email: email, password: password)
                        loading = false
                    }
                } label: {
                    if loading {
                        ProgressView()
                    } else {
                        Text("Log In")
                            .frame(maxWidth: .infinity)
                    }
                }
                .buttonStyle(.borderedProminent)
                .tint(.orange)
                .disabled(email.isEmpty || password.isEmpty || loading)
            }
            .padding()
        }
    }
}
