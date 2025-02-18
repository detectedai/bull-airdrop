-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS bull_airdrop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE bull_airdrop;

-- Users table for storing Twitter user information
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    twitter_username VARCHAR(255) NOT NULL UNIQUE,
    total_points INT DEFAULT 0,
    oauth_token VARCHAR(255),
    oauth_token_secret VARCHAR(255),
    user_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Registrations table for storing airdrop registrations
CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    x_account VARCHAR(255) NOT NULL UNIQUE,
    wallet VARCHAR(255) NOT NULL,
    reference_code VARCHAR(255),
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Interactions table for storing tweet interactions
CREATE TABLE IF NOT EXISTS interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    twitter_username VARCHAR(255) NOT NULL,
    tweet_id VARCHAR(255) NOT NULL,
    interaction_type ENUM('like', 'retweet') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_interaction (twitter_username, tweet_id, interaction_type)
);

-- Add indexes for better performance
CREATE INDEX idx_twitter_username ON users(twitter_username);
CREATE INDEX idx_x_account ON registrations(x_account);
CREATE INDEX idx_reference_code ON registrations(reference_code);
CREATE INDEX idx_interactions_user_tweet ON interactions(twitter_username, tweet_id);
