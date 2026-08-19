// Color Varables
const chartGreen = '#48CD50';
const chartRed = '#D85E44';
const chart_color_1 = '#21409B';
const chart_color_2 = '#3658C9';
const chart_color_3 = '#5D7BE6';
const chart_color_4 = '#B9C8F7';
const chartOrange = '#FF9500';
const chartPink = '#fd53bc';
const chartYellow = '#e0d317';
const chartPurple = '#AF52DE';
const chartGreenBlue = '#09bd6c';


// Color Variables
const emptyColor = '#E5EAF7';

const setDoughnutChart = (chartData) => {
    // Destructure the chartData object to extract the necessary data
    const { chart, labels, label, data, backgroundColor = emptyColor } = chartData;

    // Check if all data values are zero
    const isEmptyData = data.every((value) => value === 0);

    // If data is empty, use a single color for the entire chart
    const chartLabels = isEmptyData ? ['No Data'] : labels;
    const chartDataValues = isEmptyData ? [1] : data;
    const chartBackgroundColors = isEmptyData ? [emptyColor] : backgroundColor || [chart_color_4, chart_color_2];

    // Get the chart context
    var ctx = document.getElementById(chart).getContext('2d');

    // Create the Doughnut Chart
    var myChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartLabels,
            datasets: [{
                label: label,
                data: chartDataValues,
                backgroundColor: chartBackgroundColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            cutout: '70%', // Doughnut cutout size for inner space
            plugins: {
                legend: {
                    display: false // Hide default legend
                },
                tooltip: {
                    enabled: true
                }
            }
        }
    });

    myChart.update();
};
const setLineChart = (chartData) => {
    // Destructure the chartData object to extract the necessary data
    const { chart, labels, label, data } = chartData;

    var ctx = document.getElementById(chart).getContext('2d');

    // Destroy the previous chart instance if it exists
    if (window.myCharts && window.myCharts[chart] instanceof Chart) {
        window.myCharts[chart].destroy();
    }

    var gradient = ctx.createLinearGradient(0, 0, 0, 400); // Vertical gradient
    gradient.addColorStop(0, 'rgba(54, 162, 235, 0.3)'); // Light translucent teal (base color with alpha)
    gradient.addColorStop(1, 'rgba(38, 104, 103, 0)');   // Fully transparent teal (base color with alpha)


    const dataMax = Math.max(...data);
    const buffer = dataMax * 0.1; // Add 10% buffer above max value
    const max = Math.ceil(dataMax + buffer);
    // Optionally round stepSize to make it nice

    const stepSize = Math.ceil(max / 5);

    // Line Chart (Responsive with new color settings)
    var lineChartConfig = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels, // X-axis labels
            datasets: [{
                label: label, // Label for the dataset
                data: data, // Y-axis data points
                backgroundColor: gradient, // Light green fill
                borderColor: chart_color_2, // Line border color
                fill: true, // Fill the area under the line
                tension: 0.4, // Smooth the curve
                pointBackgroundColor: chart_color_2, // Color for the points
                pointBorderColor: chart_color_1, // Border color for the points
                pointRadius: 5, // Size of the points
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false // Hide default legend
                },
                tooltip: {
                    enabled: true
                }
            },
            scales: {
                y: {
                    display: true,
                    beginAtZero: true, // Start the Y-axis at 0
                    max: max, // Set the Y-axis maximum value to 10000
                    ticks: {
                        stepSize: stepSize, // Controls the gap between tick marks
                        callback: function (value) {
                            return value; // Append '%' symbol to the tick values
                        }
                    },
                    grid: {
                        drawBorder: false,
                        color: 'rgba(204, 204, 204, 0.1)' // Custom grid color
                    }
                },
                x: {
                    display: true,
                    grid: {
                        drawBorder: false,
                        color: 'rgba(204, 204, 204, 0.1)' // Custom grid color
                    }
                }
            }
        }
    });

    // Initialize myCharts object if it doesn't exist
    if (!window.myCharts) {
        window.myCharts = {};
    }

    // Store the chart instance using the chartId as the key
    window.myCharts[chart] = lineChartConfig;

    lineChartConfig.update();
}

const initializeStudentAssignments = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Submit', 'Not Submited'],
        label: 'Plagiarism Check',
        data: [80, 20], // Data for the chart
        backgroundColor: [chartRed, chartGreen], // Custom background colors
        text: true,
        textLine1: "80%", // Custom text for the first line
        textLine2: "Complete" // Custom text for the second line
    });
    setDoughnutChart({
        chart: 'gradeChart',
        labels: ['A Grade', 'B Grade', 'C Grade', 'D Grade'],
        label: 'Examination Grade',
        data: [20, 40, 40, 0], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });
}

const initializeStudentPaymentDetails = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Paid', 'Due'],
        label: 'Payments',
        data: [80, 20], // Data for the chart
        backgroundColor: [chartRed, chartGreen], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });
    setDoughnutChart({
        chart: 'gradeChart',
        labels: ['Bank', 'Card'],
        label: 'Payments',
        data: [20, 80], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });
}

const initializeStudentExaminations = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Present', 'Absent'],
        label: 'Attend Examination',
        data: [80, 20], // Data for the chart
        backgroundColor: [chartRed, chartGreen], // Custom background colors
        text: true,
        textLine1: "80%", // Custom text for the first line
        textLine2: "Complete" // Custom text for the second line
    });
    setDoughnutChart({
        chart: 'gradeChart',
        labels: ['A Grade', 'B Grade', 'C Grade', 'D Grade'],
        label: 'Examination Grade',
        data: [20, 40, 40, 0], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });
}

const initializeStudentAttendance = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Absent', 'Present', 'Bank Payment'],
        label: 'Lecture Attendance',
        data: [80, 20], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });

    setLineChart({
        chart: 'lineChart',
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        label: 'Monthly Attendance',
        data: [10, 20, 30, 10, 20, 30, 10], // Data for the chart
    })
};

const initializeLectureAttendance = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Absent', 'Present', 'Bank Payment'],
        label: 'Lecture Attendance',
        data: [80, 20], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2], // Custom background colors
        text: true,
        textLine1: "75%", // Custom text for the first line
        textLine2: "Performance" // Custom text for the second line
    });
    setLineChart({
        chart: 'lineChart',
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        label: 'Monthly Attendance',
        data: [10, 20, 30, 10, 20, 30, 10], // Data for the chart
    })
};

const initializeSelectedPaymentCategory = async (id) => {

    const paymentMethodChartCanvas = $("#paymentMethodChart");

    const bank = paymentMethodChartCanvas.data('bank') || 0;
    const card = paymentMethodChartCanvas.data('card') || 0;


    setDoughnutChart({
        chart: 'paymentMethodChart',
        labels: ['Bank', 'Card'],
        label: 'Payments',
        data: [bank, card], // Data for the chart
        backgroundColor: [chartOrange, chartPink], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });

    await Promise.all([
        loadSelectedPaymentCategoryPaymentStatusCount(id)
    ]);

}

const initializeSelectedSubject = async (id) => {
    await Promise.all([
        loadSubjectAssignmentsPerformance(id),
        loadSubjectQuizExamPerformance(id)
    ]);

}

const initializeSelectedSubjectAssignment = (id) => {
    fetch(`api/getSelectedAssignmentChartData.php?assignment=${id}`)
        .then((response) => response.text())
        .then((data) => {
            //console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {

                setDoughnutChart({
                    chart: 'assignmentChart',
                    labels: response.labels,
                    label: 'Assignments',
                    data: response.data,
                    backgroundColor: [chart_color_1, chartGreen, chartYellow, chartRed], // Custom background colors

                });

                setDoughnutChart({
                    chart: 'assignmentMarkChart',
                    labels: response.labels2,
                    label: 'Assignments Marking Progress',
                    data: response.data2, // Data for the chart
                    backgroundColor: [chart_color_1, chartPurple, chartPink, chartGreen], // Custom background colors
                });


            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
        });
}

const initializeSelectedSubjectExamination = (id, type) => {
    //console.log(id);
    if (type == 2) {

        fetch('api/getOnlineExamChartData.php?id=' + id)
            .then((response) => response.text())
            .then((data) => {
                //console.log(data);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {

                    setDoughnutChart({
                        chart: 'examinationChart',
                        labels: response.label,
                        label: 'Examination',
                        data: response.data, // Data for the chart
                        backgroundColor: [chart_color_1, chart_color_2], // Custom background colors

                    });
                    setDoughnutChart({
                        chart: 'examinationMarkChart',
                        labels: response.label2,
                        label: 'Examination Marking Progress',
                        data: response.data2, // Data for the chart
                        backgroundColor: [chart_color_1, chart_color_2, chart_color_3], // Custom background colors

                    });
                }

            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);

    }


}

const initializeSelectedSubjectQuiz = () => {
    setDoughnutChart({
        chart: 'quizChart',
        labels: ['Submit', 'Not-Submited'],
        label: 'Quiz',
        data: [40, 60], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
        text: true,
        textLine1: "88%", // Custom text for the first line
        textLine2: "Total Performance" // Custom text for the second line
    });
    setDoughnutChart({
        chart: 'quizMarkChart',
        labels: ['0% - 25%', '25% - 50%', '50% - 75%', '75% - 100%'],
        label: 'Quiz Marking Progress',
        data: [50, 30, 20, 10], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
        text: true,
        textLine1: "88%", // Custom text for the first line
        textLine2: "Total Performance" // Custom text for the second line
    });


}

const initializePlagiarismOverview = () => {
    setDoughnutChart({
        chart: 'plagiarismChart',
        labels: ['Physical Science', 'Computer Science', 'Food & Cookery'],
        label: 'Most Plagiarised Courses',
        data: [40, 50, 10], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3], // Custom background colors
        text: true,
        textLine1: "88%", // Custom text for the first line
        textLine2: "Total Performance" // Custom text for the second line
    });


}

const initializeSelectedPlagiarism = () => {
    setDoughnutChart({
        chart: 'plagiarismChart',
        labels: ['Uniq', 'Plagiarism'],
        label: 'Most Plagiarised Courses',
        data: [40, 60], // Data for the chart
        backgroundColor: [chartGreen, chartRed], // Custom background colors
        text: true,
        textLine1: "88%", // Custom text for the first line
        textLine2: "Total Performance" // Custom text for the second line
    });


}

const initializeDashboard = async () => {

    function generateMiniBars(values = []) {
        const max = Math.max(...values, 1);

        return values.map((value, index) => {
            const height = Math.max((value / max) * 36, 8);
            const activeClass = index === values.length - 1 ? "active" : "";

            return `<span class="stats-mini-bar ${activeClass}" style="height:${height}px;"></span>`;
        }).join("");
    }

    document.querySelectorAll(".stats-mini-chart").forEach(chart => {
        try {
            const values = JSON.parse(chart.dataset.chart || "[]");
            chart.innerHTML = generateMiniBars(values);
        } catch (e) {
            chart.innerHTML = "";
        }
    });

    await Promise.all([
        loadStudentEngagemnet(),
        loadAssignmentsPerformance(),
        loadQuizExamPerformance(),
        loadIncomeData()
    ]);

    const chartCanvases = document.querySelectorAll('canvas[id^="paymentPieChart_"]');

    chartCanvases.forEach(canvas => {
        const awaiting = parseInt(canvas.getAttribute('data-awaiting')) || 0;
        const pending = parseInt(canvas.getAttribute('data-pending')) || 0;
        const approved = parseInt(canvas.getAttribute('data-approved')) || 0;
        const rejected = parseInt(canvas.getAttribute('data-rejected')) || 0;

        // Call the setDoughnutChart method
        setDoughnutChart({
            chart: canvas.id, // Use the canvas ID
            labels: ['Awaiting', 'Pending Approval', 'Approved', 'Rejected'],
            label: 'Payments',
            data: [awaiting, pending, approved, rejected], // Data for the chart
            backgroundColor: [chartYellow, chart_color_2, chartGreen, chartRed], // Custom background colors

        });
    });
}

const initializePayments = () => {
    const chartCanvases = document.querySelectorAll('canvas[id^="paymentPieChart_"]');

    // Loop through each canvas and initialize a pie chart
    chartCanvases.forEach(canvas => {
        const pending = parseInt(canvas.getAttribute('data-pending')) || 0;
        const approved = parseInt(canvas.getAttribute('data-approved')) || 0;
        const rejected = parseInt(canvas.getAttribute('data-rejected')) || 0;

        // Call the setDoughnutChart method
        setDoughnutChart({
            chart: canvas.id, // Use the canvas ID
            labels: ['Pending', 'Approved', 'Rejected'],
            label: 'Payments',
            data: [pending, approved, rejected], // Data for the chart
            backgroundColor: ['#0061D2', '#48CD50', '#D85E44'], // Custom background colors
            text: false, // Disable text in the center
            textLine1: "", // Custom text for the first line
            textLine2: "" // Custom text for the second line
        });
    });
};

const loadStudentEngagemnet = () => {
    const condition = $("#student-engagements-condition").val() || 1;

    return new Promise((resolve) => {
        fetch('api/getStudentsEngagementsData.php?condition=' + condition)
            .then((response) => response.text())
            .then((data) => {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    setLineChart({
                        chart: 'studentEngagementlineChart',
                        labels: response.labels,
                        label: 'Student Engagement',
                        data: response.data,
                    });
                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const loadAssignmentsPerformance = () => {
    return new Promise((resolve) => {
        fetch('api/getAssignmentsPerformance.php')
            .then((response) => response.text())
            .then((data) => {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    if (response.data[0] == 0 && response.data[1] == 0 && response.data[2] == 0 && response.data[3] == 0) {

                        setDoughnutChart({
                            chart: 'assignmentChart',
                            labels: [""],
                            label: 'Assignments',
                            data: [0, 0, 0, 0], // Data for the chart
                            backgroundColor: [emptyColor, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    } else {

                        setDoughnutChart({
                            chart: 'assignmentChart',
                            labels: response.labels,
                            label: 'Assignments',
                            data: response.data, // Data for the chart
                            backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    }

                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const loadQuizExamPerformance = () => {
    return new Promise((resolve) => {
        fetch('api/getQuizPerformance.php')
            .then((response) => response.text())
            .then((data) => {
                //console.log(data);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    if (response.data[0] == 0 && response.data[1] == 0) {
                        setDoughnutChart({
                            chart: 'examChart',
                            labels: [""],
                            label: 'Quizes',
                            data: [0, 0, 0, 0], // Data for the chart
                            backgroundColor: [emptyColor, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
                        });
                    } else {
                        setDoughnutChart({
                            chart: 'examChart',
                            labels: response.labels,
                            label: 'Quizes',
                            data: response.data, // Data for the chart
                            backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    }

                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const loadIncomeData = () => {
    const condition = $("#income-condition").val() || 1;
    return new Promise((resolve) => {
        fetch('api/getIncomeData.php?condition=' + condition)
            .then((response) => response.text())
            .then((data) => {
                //console.log(data);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    setLineChart({
                        chart: "incomeLineChart",
                        labels: response.labels,
                        label: 'Income',
                        data: response.data,
                    });
                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });
}
const loadSubjectAssignmentsPerformance = (id) => {
    return new Promise((resolve) => {
        fetch(`api/getSubjectAssignmentsPerformance.php?subject=${id}`)
            .then((response) => response.text())
            .then((data) => {

                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    if (response.data[0] == 0 && response.data[1] == 0 && response.data[2] == 0 && response.data[3] == 0) {

                        setDoughnutChart({
                            chart: 'subjectAssignmentChart',
                            labels: [""],
                            label: 'Assignments',
                            data: [0, 0, 0, 0], // Data for the chart
                            backgroundColor: [emptyColor, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    } else {

                        setDoughnutChart({
                            chart: 'subjectAssignmentChart',
                            labels: response.labels,
                            label: 'Assignments',
                            data: response.data, // Data for the chart
                            backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    }

                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const loadSubjectQuizExamPerformance = (id) => {
    return new Promise((resolve) => {
        fetch(`api/getSubjectQuizPerformance.php?subject=${id}`)
            .then((response) => response.text())
            .then((data) => {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    if (response.data[0] == 0 && response.data[1] == 0) {
                        setDoughnutChart({
                            chart: 'subjectExamChart',
                            labels: [""],
                            label: 'Quizes',
                            data: [0, 0, 0, 0], // Data for the chart
                            backgroundColor: [emptyColor, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
                        });
                    } else {
                        setDoughnutChart({
                            chart: 'subjectExamChart',
                            labels: response.labels,
                            label: 'Quizes',
                            data: response.data, // Data for the chart
                            backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
                        });
                    }

                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}
const loadSelectedPaymentCategoryPaymentStatusCount = (id) => {
    return new Promise((resolve) => {
        fetch(`api/getPaymentCategoryPaymentsStatusCount.php?category=${id}`)
            .then((response) => response.text())
            .then((data) => {
                //console.log(data);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    if (response.data[0] == 0 && response.data[1] == 0) {
                        setDoughnutChart({
                            chart: 'myChart',
                            labels: [''],
                            label: 'Payments',
                            data: [0, 0, 0, 0,], // Data for the chart
                            backgroundColor: [emptyColor, chart_color_2, chart_color_3, chart_color_4],
                        });
                    } else {
                        setDoughnutChart({
                            chart: 'myChart',
                            labels: response.labels,
                            label: 'Payments',
                            data: response.data,
                            backgroundColor: [chartYellow, chartOrange, chartGreen, chartRed, chartOrange, chartGreen, chartRed, chartGreen],
                        });
                    }

                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const initializeLecturerDashboard = () => {
    fetch('api/getLecturerBusyTimelines')
        .then((response) => response.text())
        .then((data) => {
            //console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                const data = {
                    labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    datasets: [{
                        label: 'Busy Time',
                        data: response.data, // your PHP-generated data (with label field)
                        backgroundColor: 'rgba(54, 162, 235, 0.3)',
                        borderRadius: 4,
                        borderSkipped: false
                    }]
                };

                const config = {
                    type: 'bar',
                    data: data,
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.raw.label || `${context.raw.x[0]} - ${context.raw.x[1]}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'linear',
                                min: 6,
                                max: 18,
                                ticks: {
                                    callback: function (value) {
                                        const hours = Math.floor(value);
                                        const minutes = Math.round((value - hours) * 60);
                                        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Time'
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Day'
                                }
                            }
                        }
                    }
                };

                new Chart(document.getElementById('busyTimeline'), config);

            }
        })
        .catch((error) => {
            //console.log(error);
            setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
        });

}

const initializeCurrencyExchangeRates = () => {

    const today = new Date().toISOString().split("T")[0];

    const firstDay = new Date(
        new Date().getFullYear(),
        new Date().getMonth(),
        1
    ).toISOString().split("T")[0];

    document.getElementById("fromDate").value = firstDay;
    document.getElementById("toDate").value = today;

    loadCurrencyChart();

}
let currencyRateChart = null;
function loadCurrencyChart() {
    const fromDate = document.getElementById("fromDate").value;
    const toDate = document.getElementById("toDate").value;

    const data = {
        fromDate: fromDate,
        toDate: toDate
    }

    fetch("api/getCurrencyExchangeRates", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById("currencyRateChart");

            if (currencyRateChart) {
                currencyRateChart.destroy();
            }

            currencyRateChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: data.labels,
                    // Dynamic configuration mapping for each unique currency dataset
                    datasets: data.datasets.map(dataset => {
                        
                        // 1. Determine a fallback color if your PHP doesn't provide a borderColor
                        let baseColor = dataset.borderColor;
                        if (!baseColor) {
                            // Assign unique colors based on the label text if colors aren't defined in the backend
                            if (dataset.label.includes("USD")) baseColor = "rgba(255, 99, 132, 1)";    // Pink/Red
                            else if (dataset.label.includes("GBP")) baseColor = "rgba(54, 162, 235, 1)"; // Blue
                            else if (dataset.label.includes("EUR")) baseColor = "rgba(255, 159, 64, 1)";  // Orange
                            else if (dataset.label.includes("CAD")) baseColor = "rgba(255, 205, 86, 1)";  // Yellow
                            else baseColor = "rgba(75, 192, 192, 1)";                                   // Teal default
                        }

                        // Convert base color to a semi-transparent version for the gradient top slice
                        // If baseColor is an 'rgba()' string already, this safely pulls the core rgb values
                        const rgbValues = baseColor.match(/\d+/g);
                        const r = rgbValues ? rgbValues[0] : 54;
                        const g = rgbValues ? rgbValues[1] : 162;
                        const b = rgbValues ? rgbValues[2] : 235;

                        return {
                            ...dataset,
                            borderColor: baseColor,
                            pointBackgroundColor: baseColor,
                            fill: true,
                            tension: 0.4,
                            spanGaps: true,

                            // 2. Compute a dynamic matching linear gradient shadow
                            backgroundColor: function (context) {
                                const chart = context.chart;
                                const { ctx, chartArea } = chart;

                                if (!chartArea) {
                                    return null;
                                }

                                const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                                
                                // Uses the currency's specific base color with fading alphas
                                gradient.addColorStop(0, `rgba(${r}, ${g}, ${b}, 0.25)`); // Matching tint at top
                                gradient.addColorStop(1, `rgba(${r}, ${g}, ${b}, 0.00)`); // Fades out completely at bottom

                                return gradient;
                            }
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    interaction: {
                        mode: "index",
                        intersect: false
                    },

                    plugins: {
                        legend: {
                            position: "bottom",
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    if (context.raw === null) {
                                        return context.dataset.label + ": No rate";
                                    }

                                    return context.dataset.label + ": LKR. " +
                                        Number(context.raw).toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                }
                            }
                        }
                    },

                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: function (value) {
                                    return "LKR. " + value;
                                }
                            }
                        }
                    }
                }
            });
        });
}

const initializePerformance = (userId, target) => {
    loadTargetChart(userId, target);
    loadWorkedDayChart(userId);
}

let targetChart = null;
const loadTargetChart = (userId, target) => {
    const condition = $("#target-condition").val();

    fetch("api/getUserPerformance.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            id: userId,
            condition: condition,
        }),
    })
        .then((response) => response.json())
        .then((response) => {
            if (response.error) {
                setTimeout(() => {
                    showToast("error", response.error);
                }, 1000);
                return;
            }

            const pendingData = response.pending || [];
            const rejectedData = response.rejected || [];
            const inProgressData = response.inprogress || [];
            const readyData = response.ready || [];
            const enrolledData = response.enrolled || [];
            const labels = response.labels || [];

            const sumArray = (arr) => arr.reduce((total, val) => total + val, 0);

            $("#pendingTotal").text(sumArray(pendingData));
            $("#rejectedTotal").text(sumArray(rejectedData));
            $("#progressTotal").text(sumArray(inProgressData));
            $("#readyTotal").text(sumArray(readyData));
            $("#enrolledTotal").text(sumArray(enrolledData));

            let topic = "Weekly Target Chart";
            let xTitle = "Days of the Week";

            if (condition == "2") {
                topic = "Monthly Target Chart";
                xTitle = "Days of the Month";
            } else if (condition == "3") {
                topic = "Yearly Target Chart";
                xTitle = "Months of the Year";
            }

            const monthlyTarget = parseInt(target) || 0;

            const currentDate = new Date();
            const selectedYear = parseInt(response.year) || currentDate.getFullYear();
            const selectedMonth = parseInt(response.month) || (currentDate.getMonth() + 1);

            const getWeeksInMonth = (year, month) => {
                const firstDate = new Date(year, month - 1, 1);
                const lastDate = new Date(year, month, 0);
                const totalDays = lastDate.getDate();
                const firstDay = firstDate.getDay();

                return Math.ceil((firstDay + totalDays) / 7);
            };

            let targetValue = monthlyTarget;

            if (condition == "1") {
                const weekCount = getWeeksInMonth(selectedYear, selectedMonth);
                targetValue = weekCount > 0 ? Math.round(monthlyTarget / weekCount) : 0;
            } else if (condition == "2") {
                targetValue = monthlyTarget;
            } else if (condition == "3") {
                targetValue = monthlyTarget;
            }

            // very important: target data length must match labels length
            const targetLineData = labels.map(() => targetValue);

            const ctx = document.getElementById("targetChart").getContext("2d");

            if (targetChart) {
                targetChart.destroy();
            }

            targetChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: "Pending Action",
                            data: pendingData,
                            borderColor: "#ffc107",
                            backgroundColor: "rgba(255, 193, 7, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#ffc107"
                        },
                        {
                            label: "Rejected",
                            data: rejectedData,
                            borderColor: "#dc3545",
                            backgroundColor: "rgba(220, 53, 69, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#dc3545"
                        },
                        {
                            label: "In Progress",
                            data: inProgressData,
                            borderColor: "#0d6efd",
                            backgroundColor: "rgba(13, 110, 253, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#0d6efd"
                        },
                        {
                            label: "Ready to Enroll",
                            data: readyData,
                            borderColor: "#d63384",
                            backgroundColor: "rgba(214, 51, 132, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#d63384"
                        },
                        {
                            label: "Enrolled",
                            data: enrolledData,
                            borderColor: "#6f42c1",
                            backgroundColor: "rgba(111, 66, 193, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#6f42c1"
                        },
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: "index",
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: "top",
                            labels: {
                                usePointStyle: true,
                                font: { weight: "bold" }
                            }
                        },
                        title: {
                            display: true,
                            text: topic,
                            font: { size: 18, weight: "bold" }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: "Number of Students"
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: xTitle
                            }
                        }
                    }
                }
            });
        })
        .catch((error) => {
            console.log("Error:", error);
        });
};

const loadWorkedDayChart = (userId) => {
    const monthName = document.getElementById('monthName');
    const calendarDays = document.getElementById('calendarDays');
    let currentDate = new Date();

    let workingDays = [];

    fetch('api/getUserWorkingDays.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: userId
        })
    })
        .then(res => res.json())
        .then(data => {
            workingDays = data.workingDays;
            renderCalendar();
        });
    function renderCalendar() {
        calendarDays.innerHTML = '';

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        monthName.textContent = currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });

        const firstDay = new Date(year, month, 1).getDay();
        const lastDate = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) {
            const empty = document.createElement('div');
            empty.classList.add('calendar-day', 'inactive');
            calendarDays.appendChild(empty);
        }

        for (let day = 1; day <= lastDate; day++) {
            const date = new Date(year, month, day);
            const formatted = date.toLocaleDateString('en-CA', { timeZone: 'Asia/Colombo' }); // YYYY-MM-DD

            const dayCell = document.createElement('div');
            dayCell.classList.add('calendar-day');
            dayCell.textContent = day;

            // if (workingDays.includes(formatted)) {
            //     dayCell.classList.add('attended');
            //     dayCell.addEventListener('click', () => {
            //         window.location.href = `user-day-performance.php?user-id=${userId}&date=${encodeURIComponent(formatted)}`;
            //     });
            // }

            calendarDays.appendChild(dayCell);
        }
    }

    document.getElementById('prevMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    document.getElementById('nextMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });
}