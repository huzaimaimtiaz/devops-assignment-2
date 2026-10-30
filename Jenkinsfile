// Part-II: containerized automation pipeline.
//
// Triggered by a GitHub webhook push. It fetches the code with the Git
// plugin, runs a build step inside a container with the Docker Pipeline
// plugin, brings the Part-II deployment up with docker compose, verifies it,
// and emails the build result to whoever made the push.

pipeline {
    agent any

    options {
        timestamps()
        timeout(time: 15, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '25'))
    }

    triggers {
        githubPush()
    }

    environment {
        PIPELINE_COMPOSE   = 'docker-compose-pipeline.yml'
        COMPOSE_PROJECT    = 'atc-part2'
        DEPLOY_DIR         = '/srv/ci/assignment-2'
        APP_PORT           = '8200'
        // Fallbacks only. The notification goes to the author of the push;
        // these are used when GitHub hides the author behind a noreply address.
        COLLABORATOR_EMAIL = 'qasimalik@gmail.com'
        OWNER_EMAIL        = 'huzaimaimtiaz2@gmail.com'
        // Public IP of the EC2 instance, used in the notification email.
        PUBLIC_IP          = '65.1.224.36'
    }

    stages {

        stage('Checkout from GitHub') {
            steps {
                checkout scm
                script {
                    env.COMMIT_SHA   = sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()
                    env.COMMIT_MSG   = sh(script: 'git log -1 --pretty=%s', returnStdout: true).trim()
                    env.PUSHER_NAME  = sh(script: 'git log -1 --pretty=%an', returnStdout: true).trim()
                    env.PUSHER_EMAIL = sh(script: 'git log -1 --pretty=%ae', returnStdout: true).trim()
                    echo "Commit ${env.COMMIT_SHA} by ${env.PUSHER_NAME} <${env.PUSHER_EMAIL}>"
                    echo "Message: ${env.COMMIT_MSG}"
                }
            }
        }

        stage('Build inside a container') {
            steps {
                script {
                    // Docker Pipeline plugin: the build step itself runs in a
                    // container, no PHP is installed on the EC2 instance.
                    docker.image('php:8.2-cli').inside {
                        sh '''
                            set -eu
                            php -v
                            for file in app/*.php; do
                                echo "checking $file"
                                php -l "$file"
                            done
                        '''
                    }
                }
            }
        }

        stage('Deploy with docker compose') {
            steps {
                sh '''
                    set -eux

                    # Publish the freshly fetched code to the directory that
                    # the container mounts as a volume.
                    mkdir -p "$DEPLOY_DIR"
                    rm -rf "$DEPLOY_DIR/app" "$DEPLOY_DIR/db"
                    cp -a app db "$DEPLOY_DIR/"
                    cp -a "$PIPELINE_COMPOSE" "$DEPLOY_DIR/"
                    chmod -R a+rX "$DEPLOY_DIR"

                    cd "$DEPLOY_DIR"
                    docker compose -p "$COMPOSE_PROJECT" -f "$PIPELINE_COMPOSE" down || true
                    docker compose -p "$COMPOSE_PROJECT" -f "$PIPELINE_COMPOSE" up -d
                    docker compose -p "$COMPOSE_PROJECT" -f "$PIPELINE_COMPOSE" ps
                '''
            }
        }

        stage('Verify deployment') {
            steps {
                sh '''
                    set -eu
                    for attempt in $(seq 1 30); do
                        code=$(curl -s -o /dev/null -w '%{http_code}' \
                               "http://localhost:${APP_PORT}/index.php" || true)
                        echo "attempt ${attempt}: HTTP ${code}"
                        if [ "$code" = "200" ]; then
                            if curl -s "http://localhost:${APP_PORT}/index.php" | grep -q 'DB_OK'; then
                                echo "Application is up and the database is connected."
                                exit 0
                            fi
                            echo "page served but database not connected yet"
                        fi
                        sleep 5
                    done
                    echo "Application did not become healthy in time."
                    exit 1
                '''
            }
        }
    }

    post {
        always {
            script {
                def result = currentBuild.currentResult ?: 'UNKNOWN'
                def duration = currentBuild.durationString.replace(' and counting', '')

                // The build result goes back to whoever made the push, and to
                // nobody else. If GitHub hid the author behind a noreply
                // address, fall back by who the commit says it came from.
                def recipients = env.PUSHER_EMAIL
                if (!recipients || recipients.endsWith('users.noreply.github.com')) {
                    def who = (env.PUSHER_NAME ?: '').toLowerCase()
                    recipients = who.contains('huzaima') ? env.OWNER_EMAIL
                                                         : env.COLLABORATOR_EMAIL
                }
                echo "Sending build result to ${recipients}"

                def colour = (result == 'SUCCESS') ? '#1f6f54' : '#8a2f2f'

                emailext(
                    to: recipients,
                    subject: "ATC Flight Strip Console Part-II: ${result} (#${env.BUILD_NUMBER})",
                    mimeType: 'text/html',
                    body: """
                      <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#16181d">
                        <h2 style="margin:0 0 6px">ATC Flight Strip Console - Part-II build</h2>
                        <p style="margin:0 0 14px;color:#5f6672">
                          CSC418 DevOps for Cloud Computing, Assignment 2, FA23-BDS-017
                        </p>
                        <p style="font-size:16px;font-weight:bold;color:${colour};margin:0 0 14px">
                          Build result: ${result}
                        </p>
                        <table cellpadding="6" cellspacing="0"
                               style="border-collapse:collapse;font-size:14px">
                          <tr><td><b>Job</b></td><td>${env.JOB_NAME} #${env.BUILD_NUMBER}</td></tr>
                          <tr><td><b>Triggered by push from</b></td>
                              <td>${env.PUSHER_NAME ?: 'unknown'} &lt;${env.PUSHER_EMAIL ?: 'unknown'}&gt;</td></tr>
                          <tr><td><b>Commit</b></td><td>${env.COMMIT_SHA ?: 'unknown'}</td></tr>
                          <tr><td><b>Commit message</b></td><td>${env.COMMIT_MSG ?: ''}</td></tr>
                          <tr><td><b>Branch</b></td><td>${env.GIT_BRANCH ?: 'main'}</td></tr>
                          <tr><td><b>Duration</b></td><td>${duration}</td></tr>
                          <tr><td><b>Application URL</b></td>
                              <td><a href="http://${env.PUBLIC_IP}:${env.APP_PORT}/index.php">http://${env.PUBLIC_IP}:${env.APP_PORT}/index.php</a></td></tr>
                          <tr><td><b>Console log</b></td>
                              <td><a href="${env.BUILD_URL}console">${env.BUILD_URL}console</a></td></tr>
                        </table>
                        <h3 style="margin:20px 0 6px;font-size:14px">Build log (last 60 lines)</h3>
                        <pre style="background:#f4f5f7;border:1px solid #d9dde3;border-radius:4px;
                                    padding:10px;font-size:12px;white-space:pre-wrap;
                                    word-break:break-word">\${BUILD_LOG, maxLines=60, escapeHtml=true}</pre>
                        <p style="margin:14px 0 0;color:#5f6672">
                          Full console output: <a href="${env.BUILD_URL}console">${env.BUILD_URL}console</a>
                        </p>
                      </div>
                    """
                )
            }
        }
    }
}
