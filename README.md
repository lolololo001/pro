# SchoolComm - Smart Communication System for Secondary Schools

## Overview
SchoolComm is a web-based communication system designed to improve interactions between secondary boarding school staff and parents. The system helps schools share real-time updates, fee balances, academic performance, announcements, and permission requests, replacing traditional paper-based communication.

## Key Features
- **Multi-School Registration**: System supports multiple schools with dedicated admin panels
- **Real-Time Communication**: Instant updates between school staff and parents
- **Student Progress Tracking**: Academic performance monitoring and reporting
- **Fee Balance Checking**: Real-time financial information access
- **Permission Request System**: Digital management of student leave requests
- **Sentiment Analysis**: Machine learning-based analysis of parent feedback
- **School Search**: Public search functionality for registered schools

## Technology Stack
- **Backend**: PHP for web services
- **Frontend**: HTML, CSS, JavaScript with responsive design
- **Data Analysis**: Python for sentiment analysis
- **Database**: MySQL for data storage

## User Roles
- **System Administrator**: Manages the entire platform and school registrations
- **School Administrator**: Manages school-specific content and user accounts
- **Parents**: Access student information and communicate with school staff

## Installation
1. Clone the repository
2. Import the database schema
3. Configure database connection in `config.php`
4. Set up Python environment for sentiment analysis
5. Access the system through a web browser

## Project Structure
- `/assets`: CSS, JavaScript, and image files
- `/config`: Configuration files
- `/includes`: Reusable PHP components
- `/python`: Sentiment analysis scripts
- `/views`: Frontend templates
- `/controllers`: Business logic
- `/models`: Database interaction