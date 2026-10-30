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

                def summary = """ATC Flight Strip Console - Part-II build

CSC418 DevOps for Cloud Computing, Assignment 2
Huzaima Imtiaz, FA23-BDS-017

Build result : ${result}
Job          : ${env.JOB_NAME} #${env.BUILD_NUMBER}
Pushed by    : ${env.PUSHER_NAME ?: 'unknown'} <${env.PUSHER_EMAIL ?: 'unknown'}>
Commit       : ${env.COMMIT_SHA ?: 'unknown'} - ${env.COMMIT_MSG ?: ''}
Branch       : ${env.GIT_BRANCH ?: 'main'}
Duration     : ${duration}

The Part-II deployment is now running at:
  http://${env.PUBLIC_IP}:${env.APP_PORT}/index.php

Full console output:
  ${env.BUILD_URL}console

Pipeline stages: checkout from GitHub, PHP syntax check inside a
php:8.2-cli container via the Docker Pipeline plugin, docker compose
deployment with the application code attached as a volume, and an HTTP
plus database connectivity check against the running container.
"""

                // The Mailer plugin's own SMTP settings are used here, which
                // are the ones verified with "Test configuration".
                mail to: recipients,
                     subject: "ATC Flight Strip Console Part-II: ${result} (#${env.BUILD_NUMBER})",
                     body: summary
            }
        }
    }
}
