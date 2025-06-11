<!-- Add Child Modal -->
<div id="addChildModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Add Child</h2>
            <span class="close" data-dismiss="modal">&times;</span>
        </div>
        <div class="modal-body">
            <?php if (isset($_SESSION['add_child_error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo htmlspecialchars($_SESSION['add_child_error']); 
                    unset($_SESSION['add_child_error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['add_child_success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo htmlspecialchars($_SESSION['add_child_success']); 
                    unset($_SESSION['add_child_success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="add_child.php" id="addChildForm">
                <div class="form-group">
                    <label for="student_id_number">Student ID/Admission Number*</label>
                    <input type="text" id="student_id_number" name="student_id_number" class="form-control" required>
                    <small>Enter your child's ID number provided by the school (admission or registration number)</small>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Full Name*</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required>
                    <small>Enter your child's full name exactly as registered in school</small>
                </div>
                
                <div class="form-group">
                    <label for="school_id">School*</label>
                    <select name="school_id" id="school_id" class="form-control" required>
                        <option value="">-- Select School --</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Select the school your child attends</small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_child" class="btn-submit">Add Child</button>
                </div>
            </form>
            
            <div class="modal-info">
                <p><i class="fas fa-info-circle"></i> Adding your child to your account will allow you to:</p>
                <ul>
                    <li>View their academic progress</li>
                    <li>Submit permission requests</li>
                    <li>Access fee information</li>
                    <li>Receive important notifications</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.modal-info {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.modal-info p {
    color: var(--primary-color);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.modal-info ul {
    padding-left: 1.5rem;
    margin-bottom: 0;
}

.modal-info li {
    margin-bottom: 0.3rem;
    color: #666;
}

#addChildForm .form-group small {
    display: block;
    margin-top: 0.3rem;
    color: #777;
    font-size: 0.8rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const addChildForm = document.getElementById('addChildForm');
    if (addChildForm) {
        addChildForm.addEventListener('submit', function(e) {
            const studentIdNumber = document.getElementById('student_id_number').value.trim();
            const fullName = document.getElementById('full_name').value.trim();
            const schoolId = document.getElementById('school_id').value;
            
            let isValid = true;
            let errorMessage = '';
            
            if (!studentIdNumber) {
                errorMessage = 'Please enter the student ID/admission number';
                isValid = false;
            } else if (!fullName) {
                errorMessage = 'Please enter the full name';
                isValid = false;
            } else if (!schoolId) {
                errorMessage = 'Please select a school';
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessage);
            }
        });
    }
});
</script>